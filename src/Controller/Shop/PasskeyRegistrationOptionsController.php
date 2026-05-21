<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyRegistrationOptionsController;
use ThreeBRS\EnterpriseSecurityBundle\Passkey\PasskeyWebauthnSerializerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyRegistrationOptionsBuilderInterface;
use Webauthn\PublicKeyCredentialCreationOptions;

class PasskeyRegistrationOptionsController extends AbstractPasskeyRegistrationOptionsController implements PasskeyRegistrationOptionsControllerInterface
{
    public function __construct(
        protected CustomerPasskeyRegistrationOptionsBuilderInterface $optionsBuilder,
        PasskeyWebauthnSerializerInterface $serializer,
        TokenStorageInterface $tokenStorage,
        bool $enabled,
    ) {
        parent::__construct($serializer, $tokenStorage, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function buildRegistrationOptions(UserInterface $user): PublicKeyCredentialCreationOptions
    {
        \assert($user instanceof ShopUserInterface);

        return $this->optionsBuilder->build($user);
    }
}
