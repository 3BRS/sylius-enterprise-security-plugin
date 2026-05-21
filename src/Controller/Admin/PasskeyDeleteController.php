<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractPasskeyDeleteController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class PasskeyDeleteController extends AbstractPasskeyDeleteController implements PasskeyDeleteControllerInterface
{
    public const CSRF_TOKEN_ID = 'three_brs_admin_passkey_delete';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected LastAuthMethodGuardInterface $lastAuthMethodGuard,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $csrfTokenManager, $router, $enabled);
    }

    protected function getCsrfTokenId(): string
    {
        return self::CSRF_TOKEN_ID;
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function findCredentialForUser(int $id, UserInterface $user): ?object
    {
        \assert($user instanceof AdminUserInterface);

        return $this->credentialRepository->findOneByIdAndAdminUser($id, $user);
    }

    protected function canRemoveCredential(UserInterface $user): bool
    {
        \assert($user instanceof AdminUserInterface);

        return $this->lastAuthMethodGuard->canRemovePasskeyForAdminUser($user);
    }

    protected function deleteCredential(object $credential): void
    {
        $this->entityManager->remove($credential);
        $this->entityManager->flush();
    }

    protected function getPasskeyListUrl(): string
    {
        return $this->router->generate('three_brs_admin_passkey_index');
    }
}
