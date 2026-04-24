<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FirewallRedirectTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AdminUserMagicLinkTokenVerifierInterface;

class MagicLinkVerifyController implements MagicLinkVerifyControllerInterface
{
    use FirewallRedirectTrait;
    use FlashHelperTrait;

    protected const FIREWALL_NAME = 'admin';

    public function __construct(
        private AdminUserMagicLinkTokenVerifierInterface $verifier,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher,
        private RouterInterface $router,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private bool $enabled,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $magicLink = $this->verifier->verify($token);
        if ($magicLink === null) {
            $this->logger->info('three_brs.magic_link.admin.verify_failed', [
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.magic_link.invalid_or_expired');

            return new RedirectResponse($this->router->generate('three_brs_admin_magic_link_request'));
        }

        $user = $magicLink->getAdminUser();

        $magicLink->setUsedAt($this->clock->now());
        $this->entityManager->flush();

        $this->logger->info('three_brs.magic_link.admin.verify_success', [
            'user_id' => $user->getId(),
            'ip' => $request->getClientIp(),
        ]);

        $authenticatedToken = $this->authenticate($request, $user);

        $response = new RedirectResponse($this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_admin_dashboard')));

        if ($authenticatedToken instanceof TwoFactorTokenInterface) {
            return new RedirectResponse($this->router->generate('2fa_login'));
        }

        return $response;
    }

    private function authenticate(Request $request, AdminUserInterface $user): TokenInterface
    {
        $userIdentifier = $user->getUserIdentifier();
        $passport = new SelfValidatingPassport(new UserBadge($userIdentifier, static fn () => $user));
        $token = new PostAuthenticationToken($user, static::FIREWALL_NAME, $user->getRoles());

        $event = new AuthenticationTokenCreatedEvent($token, $passport);
        $this->eventDispatcher->dispatch($event);

        $resultToken = $event->getAuthenticatedToken();
        $this->tokenStorage->setToken($resultToken);

        if ($request->hasSession()) {
            $request->getSession()->set('_security_' . static::FIREWALL_NAME, serialize($resultToken));
        }

        return $resultToken;
    }
}
