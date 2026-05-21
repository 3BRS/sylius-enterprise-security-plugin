<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSocialAccountUnlinkController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class SocialAccountUnlinkController extends AbstractSocialAccountUnlinkController implements SocialAccountUnlinkControllerInterface
{
    public function __construct(
        protected CustomerSocialAccountLinkRepositoryInterface $linkRepository,
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
        return 'three_brs_shop_social_unlink_' . $provider;
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function canUnlinkProvider(UserInterface $user, string $provider): bool
    {
        \assert($user instanceof ShopUserInterface);

        return $this->guard->canUnlinkSocialForShopUser($user, $provider);
    }

    protected function deleteLinkForProvider(UserInterface $user, string $provider): bool
    {
        \assert($user instanceof ShopUserInterface);

        $link = $this->linkRepository->findOneByShopUserAndProvider($user, $provider);
        if ($link === null) {
            return false;
        }

        $this->entityManager->remove($link);
        $this->entityManager->flush();

        return true;
    }

    protected function getSocialAccountsUrl(): string
    {
        return $this->router->generate('three_brs_shop_social_accounts');
    }

    protected function getAuditChannel(): string
    {
        return 'three_brs.social_login.shop';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'user_id';
    }
}
