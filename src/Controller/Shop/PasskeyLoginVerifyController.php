<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey\CustomerPasskeyAssertionVerifierInterface;

class PasskeyLoginVerifyController implements PasskeyLoginVerifyControllerInterface
{
    use FirewallRedirectTrait;

    protected const FIREWALL_NAME = 'shop';

    public function __construct(
        protected CustomerPasskeyAssertionVerifierInterface $verifier,
        protected TokenStorageInterface $tokenStorage,
        protected EventDispatcherInterface $eventDispatcher,
        protected AuthenticationRequiredHandlerInterface $twoFactorHandler,
        protected RouterInterface $router,
        protected LoggerInterface $logger,
        protected bool $enabled,
        protected bool $skipTwoFactorWhenUserVerified,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Missing credential payload.'], Response::HTTP_BAD_REQUEST);
        }
        $credentialJson = is_array($payload) ? ($payload['credential'] ?? null) : null;
        if (!is_string($credentialJson) || $credentialJson === '') {
            return new JsonResponse(['error' => 'Missing credential payload.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->verifier->verify($credentialJson, $request->getHost());
        } catch (\Throwable $exception) {
            $this->logger->info('three_brs.passkey.shop.assertion_failed', [
                'reason' => $exception->getMessage(),
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'Passkey assertion failed.'], Response::HTTP_BAD_REQUEST);
        }

        $authenticatedToken = $this->authenticate($request, $result->getUser(), $result->isUserVerified());

        if ($authenticatedToken instanceof TwoFactorTokenInterface) {
            $twoFactorResponse = $this->twoFactorHandler->onAuthenticationRequired($request, $authenticatedToken);

            return new JsonResponse([
                'ok' => true,
                'redirect' => $twoFactorResponse->headers->has('Location')
                    ? (string) $twoFactorResponse->headers->get('Location')
                    : null,
            ]);
        }

        $redirectUrl = $this->resolveRedirectUrl($request, static::FIREWALL_NAME, $this->router->generate('sylius_shop_account_dashboard'));

        return new JsonResponse(['ok' => true, 'redirect' => $redirectUrl]);
    }

    protected function authenticate(Request $request, ShopUserInterface $user, bool $userVerified): TokenInterface
    {
        $userIdentifier = $user->getUserIdentifier();
        $passport = new SelfValidatingPassport(new UserBadge($userIdentifier, static fn () => $user));
        $token = new PostAuthenticationToken($user, static::FIREWALL_NAME, $user->getRoles());

        $resultToken = $token;
        if (!($userVerified && $this->skipTwoFactorWhenUserVerified)) {
            $event = new AuthenticationTokenCreatedEvent($token, $passport);
            $this->eventDispatcher->dispatch($event);
            $resultToken = $event->getAuthenticatedToken();
        }

        // Regenerate the session ID before authenticating to prevent session
        // fixation. Symfony's AuthenticatorManager does this automatically on a
        // successful passport check, but we go through a passport-less custom
        // login flow here and would otherwise inherit the pre-login session ID.
        if ($request->hasSession()) {
            $request->getSession()->migrate(true);
        }

        $this->tokenStorage->setToken($resultToken);

        if ($request->hasSession()) {
            $request->getSession()->set('_security_' . static::FIREWALL_NAME, serialize($resultToken));
        }

        return $resultToken;
    }
}
