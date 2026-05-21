<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyListController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use Twig\Environment;

class PasskeyController extends AbstractPasskeyListController implements PasskeyControllerInterface
{
    public function __construct(
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        TokenStorageInterface $tokenStorage,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $twig, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function findCredentialsForUser(UserInterface $user): iterable
    {
        \assert($user instanceof AdminUserInterface);

        return $this->credentialRepository->findAllByAdminUser($user);
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Passkey/index.html.twig';
    }
}
