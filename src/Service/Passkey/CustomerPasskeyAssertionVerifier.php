<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\SessionPasskeyOptionsStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

class CustomerPasskeyAssertionVerifier implements CustomerPasskeyAssertionVerifierInterface
{
    public function __construct(
        protected CustomerPasskeyCredentialRepositoryInterface $credentialRepository,
        protected EntityManagerInterface $entityManager,
        protected SessionPasskeyOptionsStorageInterface $sessionStorage,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected PasskeyValidatorFactoryInterface $validatorFactory,
        protected ClockInterface $clock,
    ) {
    }

    public function verify(string $credentialResponseJson, string $host): CustomerPasskeyAssertionResultInterface
    {
        $serializedOptions = $this->sessionStorage->consume(CustomerPasskeyAssertionOptionsBuilder::SESSION_KEY);
        if ($serializedOptions === null) {
            throw new \RuntimeException('No passkey assertion ceremony in progress.');
        }

        $options = $this->serializer->deserialize($serializedOptions, PublicKeyCredentialRequestOptions::class);
        $publicKeyCredential = $this->serializer->deserialize($credentialResponseJson, PublicKeyCredential::class);

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Expected AuthenticatorAssertionResponse from client.');
        }

        $stored = $this->credentialRepository->findOneByCredentialId($publicKeyCredential->rawId);
        if ($stored === null) {
            throw new \RuntimeException('Passkey credential not recognized.');
        }

        $source = $this->serializer->denormalize($stored->getCredentialSource(), PublicKeyCredentialSource::class);

        $updated = $this->validatorFactory->createAssertionValidator()->check(
            $source,
            $response,
            $options,
            $host,
            $source->userHandle,
        );

        $stored->setCredentialSource($this->serializer->normalize($updated));
        $stored->setLastUsedAt($this->clock->now());
        // Flush belongs here (not in caller): signCount + lastUsedAt persistence MUST be atomic with
        // the WebAuthn check above to close the replay-attack window between concurrent assertions.
        $this->entityManager->flush();

        $user = $this->resolveUser($stored);

        return new CustomerPasskeyAssertionResult($user);
    }

    protected function resolveUser(CustomerPasskeyCredentialInterface $credential): ShopUserInterface
    {
        return $credential->getShopUser();
    }
}
