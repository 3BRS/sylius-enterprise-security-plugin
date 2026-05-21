<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyListController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use Twig\Environment;

class PasskeyController extends AbstractPasskeyListController implements PasskeyControllerInterface
{
    public function __construct(
        protected CustomerPasskeyCredentialRepositoryInterface $credentialRepository,
        TokenStorageInterface $tokenStorage,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $twig, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function findCredentialsForUser(UserInterface $user): iterable
    {
        \assert($user instanceof ShopUserInterface);

        return $this->credentialRepository->findAllByShopUser($user);
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/Passkey/index.html.twig';
    }
}
