<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Challenge\CodeChallengeResult;
use ThreeBRS\EnterpriseSecurityBundle\Challenge\CodeChallengeState;
use ThreeBRS\EnterpriseSecurityBundle\Challenge\CodeChallengeStateInterface;
use ThreeBRS\EnterpriseSecurityBundle\Challenge\CodeChallengeValidatorInterface;
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

    protected const MAX_CODES = 3;

    public function __construct(
        protected OAuthLinkCodeGeneratorInterface $codeGenerator,
        protected OAuthLinkCodeEmailManagerInterface $codeEmailManager,
        protected ClockInterface $clock,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected CodeChallengeValidatorInterface $challengeValidator,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        Environment $twig,
        LoggerInterface $logger,
        UserCheckerInterface $userChecker,
    ) {
        parent::__construct($tokenStorage, $router, $twig, $logger, $userChecker);
    }

    protected function prepareChallenge(UserInterface $user, array $pending, Request $request): void
    {
        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();

        $hasValidCode = isset($pending['code_hash'], $pending['code_expires_at']) &&
            $now < (int) $pending['code_expires_at'];

        // Don't re-send on every refresh — issue a code only when none is valid yet,
        // or when the user explicitly asks for a new one.
        if ($hasValidCode && !$request->query->getBoolean('resend')) {
            return;
        }

        // Cap how many codes one linking attempt may request: unlimited resends would
        // otherwise flood the address and keep handing an attacker fresh guesses (the
        // per-code attempt limit alone resets on every newly issued code).
        if ((int) ($pending['code_issued'] ?? 0) >= static::MAX_CODES) {
            return;
        }

        $code = $this->codeGenerator->generateCode();
        $pending['code_hash'] = $this->codeGenerator->hash($code);
        $pending['code_expires_at'] = $now + static::CODE_EXPIRATION_SECONDS;
        $pending['code_attempts'] = 0;
        $pending['code_issued'] = (int) ($pending['code_issued'] ?? 0) + 1;
        $session->set($this->getConfirmPendingSessionKey(), $pending);

        $this->codeEmailManager->sendLinkCode((string) $pending['email'], $code, static::CODE_EXPIRATION_SECONDS);
    }

    protected function verifyChallenge(UserInterface $user, array $pending, Request $request): ?string
    {
        $submittedToken = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(static::CSRF_TOKEN_ID, $submittedToken))) {
            return 'three_brs.ui.social_login.confirm_link_session_expired';
        }

        // Generic expiry + attempt-limit + single-use + constant-time compare live in the
        // bundle (shared with any integrator); here we only adapt the session state to it,
        // hash the submission and map the verdict to a user-facing message.
        $state = new CodeChallengeState(
            isset($pending['code_hash']) ? (string) $pending['code_hash'] : null,
            isset($pending['code_expires_at']) ? (int) $pending['code_expires_at'] : null,
            (int) ($pending['code_attempts'] ?? 0),
        );

        $submittedCode = trim((string) $request->request->get('_code'));
        $submittedHash = $submittedCode === '' ? null : $this->codeGenerator->hash($submittedCode);

        $outcome = $this->challengeValidator->verify($state, $submittedHash, static::MAX_ATTEMPTS);
        $this->persistChallengeState($pending, $outcome->getNextState(), $request);

        return match ($outcome->getResult()) {
            CodeChallengeResult::OK => null,
            CodeChallengeResult::EXPIRED => 'three_brs.ui.social_login.code_expired',
            CodeChallengeResult::TOO_MANY_ATTEMPTS => 'three_brs.ui.social_login.code_too_many_attempts',
            CodeChallengeResult::INVALID => 'three_brs.ui.social_login.invalid_code',
        };
    }

    /**
     * Writes the post-verification challenge state back into the pending session payload,
     * dropping the code entirely once it is burned so a fresh one can be issued and the
     * spent one cannot be replayed.
     *
     * @param array<string, mixed> $pending
     */
    protected function persistChallengeState(array $pending, CodeChallengeStateInterface $state, Request $request): void
    {
        if ($state->getHash() === null) {
            unset($pending['code_hash'], $pending['code_expires_at'], $pending['code_attempts']);
        } else {
            $pending['code_hash'] = $state->getHash();
            $pending['code_expires_at'] = $state->getExpiresAt();
            $pending['code_attempts'] = $state->getAttempts();
        }

        $request->getSession()->set($this->getConfirmPendingSessionKey(), $pending);
    }
}
