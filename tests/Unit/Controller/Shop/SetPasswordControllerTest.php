<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\SetPasswordController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSessionInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;
use Twig\Environment;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\ScopedFeatureCheckerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;

#[CoversClass(SetPasswordController::class)]
class SetPasswordControllerTest extends TestCase
{
    public function testRedirectsToLoginWhenThereIsNoShopUser(): void
    {
        $controller = $this->createController(user: null, form: null, expectFlush: false);

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    public function testRedirectsToDashboardWhenPasswordLoginIsBlocked(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $controller = $this->createController(user: $user, form: null, expectFlush: false, passwordLoginBlocked: true);

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/dashboard', $response->getTargetUrl());
    }

    public function testRedirectsToChangePasswordWhenUserAlreadyHasPassword(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn('$2y$existing-hash');

        $controller = $this->createController(user: $user, form: null, expectFlush: false);

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/change-password', $response->getTargetUrl());
    }

    public function testRendersFormForPasswordlessUserOnGet(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);

        $controller = $this->createController(user: $user, form: $form, expectFlush: false);

        $response = $controller(self::request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('set-password-form', (string) $response->getContent());
    }

    public function testSetsHashedPasswordAndRedirectsToDashboard(): void
    {
        $user = $this->createMock(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);
        $user->expects(self::once())->method('setPassword')->with('hashed-new-password');

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['newPassword' => 'NewStrongPass1!']);

        $controller = $this->createController(
            user: $user,
            form: $form,
            expectFlush: true,
            hashedPassword: 'hashed-new-password',
        );

        $response = $controller(self::request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/dashboard', $response->getTargetUrl());
    }

    public function testReplacesTheTrackedSessionAfterRegeneratingTheId(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['newPassword' => 'NewStrongPass1!']);

        // Without this the live session ends up with no row, and a session with no row
        // is reachable by no revocation path at all — while the stale row stays listed
        // as active for ever.
        $stale = $this->createStub(CustomerSessionInterface::class);
        $repository = $this->createStub(CustomerSessionRepositoryInterface::class);
        $repository->method('findOneBySessionId')->willReturn($stale);

        $tracked = null;
        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::once())->method('revoke')->with($stale);
        $tracker->expects(self::once())->method('track')->willReturnCallback(
            function (ShopUserInterface $user, string $sessionId) use (&$tracked): CustomerSessionInterface {
                $tracked = $sessionId;

                return $this->createStub(CustomerSessionInterface::class);
            },
        );

        $controller = $this->createController(
            user: $user,
            form: $form,
            expectFlush: true,
            sessionTracker: $tracker,
            sessionRepository: $repository,
            sessionTrackingEnabled: true,
        );

        $request = self::request();
        $request->getSession()->start();
        $idBeforeSubmit = $request->getSession()->getId();

        $controller($request);

        // The id is what the row is found by, and track() returns the existing record
        // when it matches, so writing the pre-migrate id would revoke the old row and
        // then hand it straight back — the live session would end up with no row of its
        // own. Naming the id rather than counting the call is the difference.
        self::assertNotSame('', $idBeforeSubmit);
        self::assertNotSame($idBeforeSubmit, $request->getSession()->getId());
        self::assertSame($request->getSession()->getId(), $tracked);
    }

    public function testWritesNoSessionRowWhenTrackingIsOff(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['newPassword' => 'NewStrongPass1!']);

        $tracker = $this->createMock(CustomerSessionTrackerInterface::class);
        $tracker->expects(self::never())->method('track');
        $tracker->expects(self::never())->method('revoke');

        $controller = $this->createController(
            user: $user,
            form: $form,
            expectFlush: true,
            sessionTracker: $tracker,
            sessionTrackingEnabled: false,
        );

        $controller(self::request());
    }

    public function testRendersFormAgainWhenSubmissionIsInvalid(): void
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getPassword')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);

        $controller = $this->createController(user: $user, form: $form, expectFlush: false);

        $response = $controller(self::request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('set-password-form', (string) $response->getContent());
    }

    private function createController(
        ?object $user,
        ?FormInterface $form,
        bool $expectFlush,
        string $hashedPassword = 'hashed',
        bool $passwordLoginBlocked = false,
        ?CustomerSessionTrackerInterface $sessionTracker = null,
        ?CustomerSessionRepositoryInterface $sessionRepository = null,
        bool $sessionTrackingEnabled = false,
    ): SetPasswordController {
        $token = null;
        if ($user !== null) {
            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
        }

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $passwordLoginChecker = $this->createStub(PasswordLoginCheckerInterface::class);
        $passwordLoginChecker->method('isEnabled')->willReturn(!$passwordLoginBlocked);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn($hashedPassword);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($expectFlush ? self::once() : self::never())->method('flush');

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'sylius_shop_login' => '/login',
            'sylius_shop_account_change_password' => '/change-password',
            'sylius_shop_account_dashboard' => '/account/dashboard',
            default => '/' . $route,
        });

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form id="set-password-form"></form>');

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form ?? $this->createStub(FormInterface::class));

        return new SetPasswordController(
            $tokenStorage,
            $passwordLoginChecker,
            $passwordHasher,
            $entityManager,
            $router,
            $twig,
            $formFactory,
            $sessionTracker ?? $this->createStub(CustomerSessionTrackerInterface::class),
            $sessionRepository ?? $this->createStub(CustomerSessionRepositoryInterface::class),
            $this->makeFeature($sessionTrackingEnabled),
        );
    }

    private static function request(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    /**
     * The two switches a feature answers to are combined behind this checker, so the
     * tests stub the answer rather than the configuration flag on its own.
     */
    protected function makeFeature(bool $customerEnabled, ?bool $adminEnabled = null): ScopedFeatureCheckerInterface
    {
        $adminEnabled ??= $customerEnabled;

        $checker = $this->createStub(ScopedFeatureCheckerInterface::class);
        $checker->method('isEnabled')->willReturnCallback(
            static fn (SettingsScope $scope): bool => $scope === SettingsScope::ADMIN ? $adminEnabled : $customerEnabled,
        );

        return $checker;
    }

}
