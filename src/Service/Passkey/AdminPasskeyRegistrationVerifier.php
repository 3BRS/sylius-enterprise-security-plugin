<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyValidatorFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\SessionPasskeyOptionsStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredentialInterface;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

class AdminPasskeyRegistrationVerifier implements AdminPasskeyRegistrationVerifierInterface
{
    public function __construct(
        protected SessionPasskeyOptionsStorageInterface $sessionStorage,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected PasskeyValidatorFactoryInterface $validatorFactory,
    ) {
    }

    public function verifyAndCreate(
        AdminUserInterface $user,
        string $credentialResponseJson,
        string $label,
        string $host,
    ): AdminUserPasskeyCredentialInterface {
        $serializedOptions = $this->sessionStorage->consume(AdminPasskeyRegistrationOptionsBuilder::SESSION_KEY);
        if ($serializedOptions === null) {
            throw new \RuntimeException('No passkey registration ceremony in progress.');
        }

        $options = $this->serializer->deserialize($serializedOptions, PublicKeyCredentialCreationOptions::class);
        $publicKeyCredential = $this->serializer->deserialize($credentialResponseJson, PublicKeyCredential::class);

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Expected AuthenticatorAttestationResponse from client.');
        }

        $source = $this->validatorFactory->createAttestationValidator()->check($response, $options, $host);

        $credential = new AdminUserPasskeyCredential();
        $credential->setAdminUser($user);
        $credential->setCredentialId($source->publicKeyCredentialId);
        $credential->setCredentialSource($this->serializer->normalize($source));
        $credential->setLabel($label);

        return $credential;
    }
}
