<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractOAuthConfirmLinkController;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthLinkCodeGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\OAuthLinkCodeEmailManagerInterface;
use Twig\Environment;

/**
 * Proves ownership of the matched local account with a one-time code emailed to that
 * account's address (instead of its password), so the link also works for accounts that
 * were created passwordless (OAuth / magic link / passkey).
 */
abstract class AbstractOAuthLinkCodeConfirmLinkController extends AbstractOAuthConfirmLinkController
{
    public const CSRF_TOKEN_ID = 'three_brs_oauth_confirm_link';

    protected const CODE_EXPIRATION_SECONDS = 600;

    protected const MAX_ATTEMPTS = 5;

    public function __construct(
        protected OAuthLinkCodeGeneratorInterface $codeGenerator,
        protected OAuthLinkCodeEmailManagerInterface $codeEmailManager,
        protected ClockInterface $clock,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($tokenStorage, $router, $twig, $logger);
    }

    protected function prepareChallenge(UserInterface $user, array $pending, Request $request): void
    {
        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();

        $hasValidCode = isset($pending['code_hash'], $pending['code_expires_at'])
            && $now < (int) $pending['code_expires_at'];

        // Don't re-send on every refresh — issue a code only when none is valid yet,
        // or when the user explicitly asks for a new one.
        if ($hasValidCode && !$request->query->getBoolean('resend')) {
            return;
        }

        $code = $this->codeGenerator->generateCode();
        $pending['code_hash'] = $this->codeGenerator->hash($code);
        $pending['code_expires_at'] = $now + static::CODE_EXPIRATION_SECONDS;
        $pending['code_attempts'] = 0;
        $session->set($this->getConfirmPendingSessionKey(), $pending);

        $this->codeEmailManager->sendLinkCode((string) $pending['email'], $code, static::CODE_EXPIRATION_SECONDS);
    }

    protected function verifyChallenge(UserInterface $user, array $pending, Request $request): ?string
    {
        $submittedToken = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(static::CSRF_TOKEN_ID, $submittedToken))) {
            return 'three_brs.ui.social_login.confirm_link_session_expired';
        }

        $hasCode = isset($pending['code_hash'], $pending['code_expires_at']);
        if (!$hasCode || $this->clock->now()->getTimestamp() >= (int) $pending['code_expires_at']) {
            return 'three_brs.ui.social_login.code_expired';
        }

        $session = $request->getSession();
        $attempts = (int) ($pending['code_attempts'] ?? 0) + 1;
        $pending['code_attempts'] = $attempts;
        $session->set($this->getConfirmPendingSessionKey(), $pending);

        // A 6-digit code is only 1,000,000 combinations — cap the guesses, then burn it.
        if ($attempts > static::MAX_ATTEMPTS) {
            unset($pending['code_hash'], $pending['code_expires_at'], $pending['code_attempts']);
            $session->set($this->getConfirmPendingSessionKey(), $pending);

            return 'three_brs.ui.social_login.code_too_many_attempts';
        }

        $submittedCode = trim((string) $request->request->get('_code'));
        if ($submittedCode === '' || !hash_equals((string) $pending['code_hash'], $this->codeGenerator->hash($submittedCode))) {
            return 'three_brs.ui.social_login.invalid_code';
        }

        return null;
    }
}
