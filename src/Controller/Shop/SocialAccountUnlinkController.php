<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class SocialAccountUnlinkController implements SocialAccountUnlinkControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        private Security $security,
        private CustomerSocialAccountLinkRepositoryInterface $linkRepository,
        private LastAuthMethodGuardInterface $guard,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private RouterInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, string $provider): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedException();
        }

        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('three_brs_shop_social_unlink_' . $provider, $token))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->guard->canUnlinkSocialForShopUser($user, $provider)) {
            $this->logger->info('three_brs.social_login.shop.unlink_refused_last_method', [
                'provider' => $provider,
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.cannot_unlink_last_method');

            return new RedirectResponse($this->router->generate('three_brs_shop_social_accounts'));
        }

        $link = $this->linkRepository->findOneByShopUserAndProvider($user, $provider);
        if ($link !== null) {
            $this->entityManager->remove($link);
            $this->entityManager->flush();
            $this->logger->info('three_brs.social_login.shop.unlinked', [
                'provider' => $provider,
                'user_id' => $user->getId(),
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlashMessage($request, 'success', 'three_brs.ui.social_login.unlinked');
        }

        return new RedirectResponse($this->router->generate('three_brs_shop_social_accounts'));
    }
}
