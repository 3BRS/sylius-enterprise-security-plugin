<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredentialInterface;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

class CustomerPasskeyRegistrationVerifier implements CustomerPasskeyRegistrationVerifierInterface
{
    public function __construct(
        protected PasskeyOptionsSessionStorageInterface $sessionStorage,
        protected PasskeyWebauthnSerializerInterface $serializer,
        protected PasskeyValidatorFactoryInterface $validatorFactory,
    ) {
    }

    public function verifyAndCreate(
        ShopUserInterface $user,
        string $credentialResponseJson,
        string $label,
        string $host,
    ): CustomerPasskeyCredentialInterface {
        $serializedOptions = $this->sessionStorage->consume(CustomerPasskeyRegistrationOptionsBuilder::SESSION_KEY);
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

        $credential = new CustomerPasskeyCredential();
        $credential->setShopUser($user);
        $credential->setCredentialId($source->publicKeyCredentialId);
        $credential->setCredentialSource($this->serializer->normalize($source));
        $credential->setAaguid($source->aaguid->toRfc4122());
        $credential->setLabel($label);

        return $credential;
    }
}
