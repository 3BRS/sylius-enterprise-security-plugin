<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSocialAccountUnlinkController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class SocialAccountUnlinkController extends AbstractSocialAccountUnlinkController implements SocialAccountUnlinkControllerInterface
{
    public function __construct(
        protected AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
        protected LastAuthMethodGuardInterface $guard,
        protected EntityManagerInterface $entityManager,
        Security $security,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        LoggerInterface $logger,
    ) {
        parent::__construct($security, $csrfTokenManager, $router, $logger);
    }

    protected function getCsrfTokenId(string $provider): string
    {
        return 'three_brs_admin_social_unlink_' . $provider;
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function canUnlinkProvider(UserInterface $user, string $provider): bool
    {
        \assert($user instanceof AdminUserInterface);

        return $this->guard->canUnlinkSocialForAdminUser($user, $provider);
    }

    protected function deleteLinkForProvider(UserInterface $user, string $provider): bool
    {
        \assert($user instanceof AdminUserInterface);

        $link = $this->linkRepository->findOneByAdminUserAndProvider($user, $provider);
        if ($link === null) {
            return false;
        }

        $this->entityManager->remove($link);
        $this->entityManager->flush();

        return true;
    }

    protected function getSocialAccountsUrl(): string
    {
        return $this->router->generate('three_brs_admin_social_accounts');
    }

    protected function getAuditChannel(): string
    {
        return 'three_brs.social_login.admin';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'admin_id';
    }
}
