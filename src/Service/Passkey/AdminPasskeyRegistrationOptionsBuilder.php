<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

use Cose\Algorithms;
use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialUserEntity;

class AdminPasskeyRegistrationOptionsBuilder implements AdminPasskeyRegistrationOptionsBuilderInterface
{
    public const SESSION_KEY = 'admin.registration_options';

    public function __construct(
        protected PasskeyRelyingPartyEntityFactoryInterface $relyingPartyEntityFactory,
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected PasskeyOptionsSessionStorageInterface $sessionStorage,
        protected PasskeyWebauthnSerializerInterface $serializer,
    ) {
    }

    public function build(AdminUserInterface $user): PublicKeyCredentialCreationOptions
    {
        $userEntity = PublicKeyCredentialUserEntity::create(
            (string) $user->getEmail(),
            (string) $user->getId(),
            (string) ($user->getUsername() ?? $user->getEmail()),
        );

        $excludeCredentials = [];
        foreach ($this->credentialRepository->findAllByAdminUser($user) as $existing) {
            $excludeCredentials[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $existing->getCredentialId(),
            );
        }

        $options = PublicKeyCredentialCreationOptions::create(
            $this->relyingPartyEntityFactory->create(),
            $userEntity,
            random_bytes(32),
            [
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS256),
            ],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            'none',
            $excludeCredentials,
            60_000,
        );

        $this->sessionStorage->store(static::SESSION_KEY, $this->serializer->serialize($options));

        return $options;
    }
}
