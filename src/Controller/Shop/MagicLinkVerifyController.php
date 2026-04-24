<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\CustomerMagicLinkTokenVerifierInterface;

class MagicLinkVerifyController implements MagicLinkVerifyControllerInterface
{
    use FirewallRedirectTrait;
    use FlashHelperTrait;

    protected const FIREWALL_NAME = 'shop';

    public function __construct(
        private CustomerMagicLinkTokenVerifierInterface $verifier,
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher,
        private AuthenticationRequiredHandlerInterface $twoFactorHandler,
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

        // Post-2FA redirect-back idempotency: scheb/2fa's prepare_on_login saves the
        // current URL as the post-2FA target_path, so once 2FA succeeds the user lands
        // back here. At that point the magic link has already done its job.
        if ($this->isFullyAuthenticatedShopUser($this->tokenStorage->getToken())) {
            return new RedirectResponse($this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_shop_account_dashboard')));
        }

        $magicLink = $this->verifier->verify($token);
        if ($magicLink === null) {
            $this->logger->info('three_brs.magic_link.shop.verify_failed', [
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlashMessage($request, 'error', 'three_brs.ui.magic_link.invalid_or_expired');

            return new RedirectResponse($this->router->generate('three_brs_shop_magic_link_request'));
        }

        $user = $magicLink->getShopUser();

        $magicLink->setUsedAt($this->clock->now());
        $this->entityManager->flush();

        $this->logger->info('three_brs.magic_link.shop.verify_success', [
            'user_id' => $user->getId(),
            'ip' => $request->getClientIp(),
        ]);

        $authenticatedToken = $this->authenticate($request, $user);

        if ($authenticatedToken instanceof TwoFactorTokenInterface) {
            return $this->twoFactorHandler->onAuthenticationRequired($request, $authenticatedToken);
        }

        return new RedirectResponse($this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_shop_account_dashboard')));
    }

    private function isFullyAuthenticatedShopUser(?TokenInterface $token): bool
    {
        return $token !== null &&
            !$token instanceof TwoFactorTokenInterface &&
            $token->getUser() instanceof ShopUserInterface;
    }

    private function authenticate(Request $request, ShopUserInterface $user): TokenInterface
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
