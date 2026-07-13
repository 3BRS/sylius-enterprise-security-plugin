<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Challenge\CodeChallengeValidator;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthLinkCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\SocialAccountLinkRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\AbstractOAuthLinkCodeConfirmLinkController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\OAuthLinkCodeEmailManagerInterface;
use Twig\Environment;

#[CoversClass(AbstractOAuthLinkCodeConfirmLinkController::class)]
class OAuthLinkCodeConfirmLinkControllerTest extends TestCase
{
    protected const NOW = 1700000000;

    protected const GENERATED_CODE = '123456';

    // -- prepareChallenge ----------------------------------------------------

    public function testPrepareIssuesCodeStoresChallengeStateAndEmailsIt(): void
    {
        $emailManager = $this->createMock(OAuthLinkCodeEmailManagerInterface::class);
        $emailManager->expects(self::once())
            ->method('sendLinkCode')
            ->with('user@example.com', self::GENERATED_CODE, 600);

        $controller = $this->makeController($emailManager);
        $request = $this->getRequest();

        $controller->callPrepare($this->user(), $this->pending(), $request);

        $pending = (array) $request->getSession()->get(OAuthLinkCodeConfirmLinkTestController::SESSION_KEY);
        self::assertSame('h:' . self::GENERATED_CODE, $pending['code_hash']);
        self::assertSame(self::NOW + 600, $pending['code_expires_at']);
        self::assertSame(0, $pending['code_attempts']);
        self::assertSame(1, $pending['code_issued']);
    }

    public function testPrepareDoesNotReissueWhenAValidCodeAlreadyExists(): void
    {
        // A page refresh must not e-mail a fresh code on every GET.
        $emailManager = $this->createMock(OAuthLinkCodeEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendLinkCode');

        $controller = $this->makeController($emailManager);

        $controller->callPrepare($this->user(), $this->pendingWithCode([
            'code_expires_at' => self::NOW + 100,
        ]), $this->getRequest());
    }

    public function testPrepareReissuesAnExpiredCode(): void
    {
        $emailManager = $this->createMock(OAuthLinkCodeEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendLinkCode');

        $controller = $this->makeController($emailManager);
        $request = $this->getRequest();

        $controller->callPrepare($this->user(), $this->pendingWithCode([
            'code_expires_at' => self::NOW - 1,
            'code_issued' => 1,
        ]), $request);

        $pending = (array) $request->getSession()->get(OAuthLinkCodeConfirmLinkTestController::SESSION_KEY);
        self::assertSame(2, $pending['code_issued']);
    }

    public function testPrepareReissuesWhenResendIsRequested(): void
    {
        $emailManager = $this->createMock(OAuthLinkCodeEmailManagerInterface::class);
        $emailManager->expects(self::once())->method('sendLinkCode');

        $controller = $this->makeController($emailManager);
        $request = $this->getRequest(['resend' => '1']);

        $controller->callPrepare($this->user(), $this->pendingWithCode([
            'code_expires_at' => self::NOW + 100,
            'code_issued' => 1,
        ]), $request);

        $pending = (array) $request->getSession()->get(OAuthLinkCodeConfirmLinkTestController::SESSION_KEY);
        self::assertSame(2, $pending['code_issued']);
    }

    public function testPrepareStopsIssuingOnceTheResendCapIsReached(): void
    {
        // Security fix: unlimited resends would flood the inbox and keep handing an
        // attacker fresh codes (each new code resets the per-code attempt counter).
        $emailManager = $this->createMock(OAuthLinkCodeEmailManagerInterface::class);
        $emailManager->expects(self::never())->method('sendLinkCode');

        $controller = $this->makeController($emailManager);

        $controller->callPrepare($this->user(), $this->pendingWithCode([
            'code_expires_at' => self::NOW + 100,
            'code_issued' => 3,
        ]), $this->getRequest(['resend' => '1']));
    }

    // -- verifyChallenge -----------------------------------------------------

    public function testVerifyRejectsAnInvalidCsrfToken(): void
    {
        $controller = $this->makeController(csrfValid: false);

        $error = $controller->callVerify(
            $this->user(),
            $this->pendingWithCode(['code_expires_at' => self::NOW + 100]),
            $this->postRequest(['_csrf_token' => 'bad', '_code' => self::GENERATED_CODE]),
        );

        self::assertSame('three_brs.ui.social_login.confirm_link_session_expired', $error);
    }

    public function testVerifyReportsExpiredWhenNoCodeWasIssued(): void
    {
        $controller = $this->makeController();

        $error = $controller->callVerify(
            $this->user(),
            $this->pending(),
            $this->postRequest(['_csrf_token' => 'ok', '_code' => self::GENERATED_CODE]),
        );

        self::assertSame('three_brs.ui.social_login.code_expired', $error);
    }

    public function testVerifyReportsExpiredWhenTheCodeHasExpired(): void
    {
        $controller = $this->makeController();

        $error = $controller->callVerify(
            $this->user(),
            $this->pendingWithCode(['code_expires_at' => self::NOW - 1]),
            $this->postRequest(['_csrf_token' => 'ok', '_code' => self::GENERATED_CODE]),
        );

        self::assertSame('three_brs.ui.social_login.code_expired', $error);
    }

    public function testVerifyRejectsAWrongCodeAndCountsTheAttempt(): void
    {
        $controller = $this->makeController();
        $request = $this->postRequest(['_csrf_token' => 'ok', '_code' => '000000']);

        $error = $controller->callVerify(
            $this->user(),
            $this->pendingWithCode(['code_expires_at' => self::NOW + 100, 'code_attempts' => 0]),
            $request,
        );

        self::assertSame('three_brs.ui.social_login.invalid_code', $error);
        $pending = (array) $request->getSession()->get(OAuthLinkCodeConfirmLinkTestController::SESSION_KEY);
        self::assertSame(1, $pending['code_attempts']);
    }

    public function testVerifyRejectsAnEmptyCode(): void
    {
        $controller = $this->makeController();

        $error = $controller->callVerify(
            $this->user(),
            $this->pendingWithCode(['code_expires_at' => self::NOW + 100]),
            $this->postRequest(['_csrf_token' => 'ok', '_code' => '']),
        );

        self::assertSame('three_brs.ui.social_login.invalid_code', $error);
    }

    public function testVerifyAcceptsTheCorrectCode(): void
    {
        $controller = $this->makeController();

        $error = $controller->callVerify(
            $this->user(),
            $this->pendingWithCode(['code_expires_at' => self::NOW + 100]),
            $this->postRequest(['_csrf_token' => 'ok', '_code' => self::GENERATED_CODE]),
        );

        self::assertNull($error);
    }

    public function testVerifyBurnsTheCodeAfterTooManyAttempts(): void
    {
        $controller = $this->makeController();
        $request = $this->postRequest(['_csrf_token' => 'ok', '_code' => '000000']);

        // 5 attempts already spent; this 6th one trips the limit and burns the code.
        $error = $controller->callVerify(
            $this->user(),
            $this->pendingWithCode(['code_expires_at' => self::NOW + 100, 'code_attempts' => 5]),
            $request,
        );

        self::assertSame('three_brs.ui.social_login.code_too_many_attempts', $error);
        $pending = (array) $request->getSession()->get(OAuthLinkCodeConfirmLinkTestController::SESSION_KEY);
        self::assertArrayNotHasKey('code_hash', $pending);
        self::assertArrayNotHasKey('code_expires_at', $pending);
    }

    // -- helpers -------------------------------------------------------------

    protected function makeController(
        ?OAuthLinkCodeEmailManagerInterface $emailManager = null,
        bool $csrfValid = true,
    ): OAuthLinkCodeConfirmLinkTestController {
        $generator = $this->createStub(OAuthLinkCodeGeneratorInterface::class);
        $generator->method('generateCode')->willReturn(self::GENERATED_CODE);
        $generator->method('hash')->willReturnCallback(static fn (string $plainCode): string => 'h:' . $plainCode);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('@' . self::NOW));

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        return new OAuthLinkCodeConfirmLinkTestController(
            $generator,
            $emailManager ?? $this->createStub(OAuthLinkCodeEmailManagerInterface::class),
            $clock,
            $csrf,
            new CodeChallengeValidator($clock),
            $this->createStub(TokenStorageInterface::class),
            $this->createStub(RouterInterface::class),
            $this->createStub(Environment::class),
            new NullLogger(),
            $this->createStub(UserCheckerInterface::class),
        );
    }

    protected function user(): UserInterface
    {
        return $this->createStub(UserInterface::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pending(): array
    {
        return [
            'email' => 'user@example.com',
            'provider' => 'google',
            'provider_user_id' => 'pid-1',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function pendingWithCode(array $overrides = []): array
    {
        return array_merge($this->pending(), [
            'code_hash' => 'h:' . self::GENERATED_CODE,
            'code_expires_at' => self::NOW + 100,
            'code_attempts' => 0,
            'code_issued' => 1,
        ], $overrides);
    }

    /**
     * @param array<string, string> $query
     */
    protected function getRequest(array $query = []): Request
    {
        return $this->withSession(Request::create('/confirm', 'GET', $query));
    }

    /**
     * @param array<string, string> $parameters
     */
    protected function postRequest(array $parameters): Request
    {
        return $this->withSession(Request::create('/confirm', 'POST', $parameters));
    }

    protected function withSession(Request $request): Request
    {
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}

/**
 * Concrete subclass that fills in the orchestration hooks left abstract by the bundle
 * base and exposes the two challenge hooks under test as public proxies. Kept in this
 * file because the plugin's test PSR-4 mapping does not cover {@code tests/Unit}.
 */
class OAuthLinkCodeConfirmLinkTestController extends AbstractOAuthLinkCodeConfirmLinkController
{
    public const SESSION_KEY = 'three_brs_oauth_confirm_pending';

    /**
     * @param array<string, mixed> $pending
     */
    public function callPrepare(UserInterface $user, array $pending, Request $request): void
    {
        $this->prepareChallenge($user, $pending, $request);
    }

    /**
     * @param array<string, mixed> $pending
     */
    public function callVerify(UserInterface $user, array $pending, Request $request): ?string
    {
        return $this->verifyChallenge($user, $pending, $request);
    }

    protected function getConfirmPendingSessionKey(): string
    {
        return self::SESSION_KEY;
    }

    protected function getFirewallName(): string
    {
        return 'shop';
    }

    protected function getLoginRoute(): string
    {
        return 'login';
    }

    protected function getDashboardUrl(): string
    {
        return '/account';
    }

    protected function getTemplate(): string
    {
        return '@Foo/confirm.html.twig';
    }

    protected function getAuditChannel(): string
    {
        return 'test.confirm';
    }

    protected function getAuditUserIdKey(): string
    {
        return 'user_id';
    }

    protected function findUserByEmail(string $email): ?UserInterface
    {
        return null;
    }

    protected function findExistingLink(string $provider, string $providerUserId): ?SocialAccountLinkRecordInterface
    {
        return null;
    }

    protected function isLinkOwnedByUser(SocialAccountLinkRecordInterface $existing, UserInterface $user): bool
    {
        return false;
    }

    protected function linkExistingUser(UserInterface $user, OAuthUserInfoInterface $info): void
    {
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
    }
}
