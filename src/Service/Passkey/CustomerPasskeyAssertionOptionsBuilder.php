<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyRelyingPartyEntityFactoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\SessionPasskeyOptionsStorageInterface;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;

class CustomerPasskeyAssertionOptionsBuilder implements CustomerPasskeyAssertionOptionsBuilderInterface
{
    public const SESSION_KEY = 'shop.assertion_options';

    public function __construct(
        protected PasskeyRelyingPartyEntityFactoryInterface $relyingPartyEntityFactory,
        protected SessionPasskeyOptionsStorageInterface $sessionStorage,
        protected PasskeyWebauthnSerializerInterface $serializer,
    ) {
    }

    public function build(): PublicKeyCredentialRequestOptions
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->relyingPartyEntityFactory->getRpId(),
            [],
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        );

        $this->sessionStorage->store(static::SESSION_KEY, $this->serializer->serialize($options));

        return $options;
    }
}
