<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyRegistrationOptionsController;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\AdminPasskeyRegistrationOptionsBuilderInterface;
use Webauthn\PublicKeyCredentialCreationOptions;

class PasskeyRegistrationOptionsController extends AbstractPasskeyRegistrationOptionsController implements PasskeyRegistrationOptionsControllerInterface
{
    public function __construct(
        protected AdminPasskeyRegistrationOptionsBuilderInterface $optionsBuilder,
        PasskeyWebauthnSerializerInterface $serializer,
        TokenStorageInterface $tokenStorage,
        bool $enabled,
    ) {
        parent::__construct($serializer, $tokenStorage, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function buildRegistrationOptions(UserInterface $user): PublicKeyCredentialCreationOptions
    {
        \assert($user instanceof AdminUserInterface);

        return $this->optionsBuilder->build($user);
    }
}
