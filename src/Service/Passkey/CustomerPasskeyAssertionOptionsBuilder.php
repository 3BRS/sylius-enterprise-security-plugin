<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;

class CustomerPasskeyAssertionOptionsBuilder implements CustomerPasskeyAssertionOptionsBuilderInterface
{
    public const SESSION_KEY = 'shop.assertion_options';

    public function __construct(
        protected PasskeyRelyingPartyEntityFactoryInterface $relyingPartyEntityFactory,
        protected PasskeyOptionsSessionStorageInterface $sessionStorage,
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
