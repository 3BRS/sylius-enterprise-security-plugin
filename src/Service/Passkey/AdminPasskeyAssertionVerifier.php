<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

class AdminPasskeyAssertionVerifier implements AdminPasskeyAssertionVerifierInterface
{
    public function __construct(
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected EntityManagerInterface $entityManager,
        protected SessionPasskeyOptionsStorageInterface $sessionStorage,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected PasskeyValidatorFactoryInterface $validatorFactory,
        protected ClockInterface $clock,
    ) {
    }

    public function verify(string $credentialResponseJson, string $host): AdminPasskeyAssertionResultInterface
    {
        $serializedOptions = $this->sessionStorage->consume(AdminPasskeyAssertionOptionsBuilder::SESSION_KEY);
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
        $userVerified = $response->authenticatorData->isUserVerified();

        return new AdminPasskeyAssertionResult($user, $userVerified);
    }

    protected function resolveUser(AdminUserPasskeyCredentialInterface $credential): AdminUserInterface
    {
        return $credential->getAdminUser();
    }
}
