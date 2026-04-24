<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class SocialAccountUnlinkController implements SocialAccountUnlinkControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        private Security $security,
        private AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
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
        if (!$user instanceof AdminUserInterface) {
            throw new AccessDeniedException();
        }

        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('three_brs_admin_social_unlink_' . $provider, $token))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->guard->canUnlinkSocialForAdminUser($user, $provider)) {
            $this->logger->info('three_brs.social_login.admin.unlink_refused_last_method', [
                'provider' => $provider,
                'admin_id' => $user->getId(),
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.social_login.cannot_unlink_last_method');

            return new RedirectResponse($this->router->generate('three_brs_admin_social_accounts'));
        }

        $link = $this->linkRepository->findOneByAdminUserAndProvider($user, $provider);
        if ($link !== null) {
            $this->entityManager->remove($link);
            $this->entityManager->flush();
            $this->logger->info('three_brs.social_login.admin.unlinked', [
                'provider' => $provider,
                'admin_id' => $user->getId(),
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlashMessage($request, 'success', 'three_brs.ui.social_login.unlinked');
        }

        return new RedirectResponse($this->router->generate('three_brs_admin_social_accounts'));
    }
}
