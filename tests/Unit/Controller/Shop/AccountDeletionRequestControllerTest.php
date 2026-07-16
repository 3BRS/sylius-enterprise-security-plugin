<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Controller\Shop;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop\AccountDeletionRequestController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;
use Twig\Environment;

#[CoversClass(AccountDeletionRequestController::class)]
class AccountDeletionRequestControllerTest extends TestCase
{
    public function testRequestsTheDeletionOnTheAcknowledgementAloneWhenPasswordLoginIsOff(): void
    {
        $customer = $this->createStub(CustomerInterface::class);

        $deletionService = $this->createMock(CustomerDeletionServiceInterface::class);
        $deletionService->expects(self::once())->method('requestDeletion')->with($customer);

        // No password is asked for and none is verified — the checkbox is the whole confirmation.
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('isPasswordValid');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($this->token($this->shopUser($customer)));
        $tokenStorage->expects(self::once())->method('setToken')->with(null);

        $controller = $this->createController(
            passwordLoginEnabled: false,
            form: $this->submittedForm(valid: true),
            deletionService: $deletionService,
            tokenStorage: $tokenStorage,
            passwordHasher: $passwordHasher,
        );

        $response = $controller($this->request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/homepage', $response->getTargetUrl());
    }

    public function testDoesNotRequestTheDeletionWhenTheFormIsNotConfirmed(): void
    {
        $deletionService = $this->createMock(CustomerDeletionServiceInterface::class);
        $deletionService->expects(self::never())->method('requestDeletion');

        $controller = $this->createController(
            passwordLoginEnabled: false,
            form: $this->submittedForm(valid: false),
            deletionService: $deletionService,
        );

        $response = $controller($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('deletion-form', (string) $response->getContent());
    }

    public function testStillDemandsTheCurrentPasswordWhilePasswordLoginIsOn(): void
    {
        $deletionService = $this->createMock(CustomerDeletionServiceInterface::class);
        $deletionService->expects(self::never())->method('requestDeletion');

        // The bundle's flow runs: it reads the password field and refuses a wrong one.
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('isPasswordValid')->willReturn(false);

        $controller = $this->createController(
            passwordLoginEnabled: true,
            form: $this->submittedForm(valid: true, currentPassword: 'wrong-password'),
            deletionService: $deletionService,
            passwordHasher: $passwordHasher,
        );

        $response = $controller($this->request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/account/delete', $response->getTargetUrl());
    }

    public function testIsNotFoundWhenTheFeatureIsDisabled(): void
    {
        $controller = $this->createController(
            passwordLoginEnabled: false,
            form: $this->submittedForm(valid: true),
            enabled: false,
        );

        $this->expectException(NotFoundHttpException::class);

        $controller($this->request());
    }

    public function testIsNotFoundForAGuestVisitor(): void
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $controller = $this->createController(
            passwordLoginEnabled: false,
            form: $this->submittedForm(valid: true),
            tokenStorage: $tokenStorage,
        );

        $this->expectException(NotFoundHttpException::class);

        $controller($this->request());
    }

    protected function createController(
        bool $passwordLoginEnabled,
        FormInterface $form,
        ?CustomerDeletionServiceInterface $deletionService = null,
        ?TokenStorageInterface $tokenStorage = null,
        ?UserPasswordHasherInterface $passwordHasher = null,
        bool $enabled = true,
    ): AccountDeletionRequestController {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        if ($tokenStorage === null) {
            $tokenStorage = $this->createStub(TokenStorageInterface::class);
            $tokenStorage->method('getToken')->willReturn(
                $this->token($this->shopUser($this->createStub(CustomerInterface::class))),
            );
        }

        $passwordLoginChecker = $this->createStub(PasswordLoginCheckerInterface::class);
        $passwordLoginChecker->method('isEnabled')->willReturn($passwordLoginEnabled);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route): string => $route === 'sylius_shop_homepage' ? '/homepage' : '/account/delete',
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form id="deletion-form"></form>');

        return new AccountDeletionRequestController(
            formFactory: $formFactory,
            deletionService: $deletionService ?? $this->createStub(CustomerDeletionServiceInterface::class),
            passwordLoginChecker: $passwordLoginChecker,
            passwordHasher: $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class),
            tokenStorage: $tokenStorage,
            router: $router,
            twig: $twig,
            enabled: $enabled,
        );
    }

    protected function submittedForm(bool $valid, ?string $currentPassword = null): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn($valid);
        $form->method('createView')->willReturn(new FormView());

        $passwordField = $this->createStub(FormInterface::class);
        $passwordField->method('getData')->willReturn($currentPassword);
        $form->method('get')->willReturn($passwordField);

        return $form;
    }

    protected function shopUser(CustomerInterface $customer): ShopUserInterface
    {
        $user = $this->createStub(ShopUserInterface::class);
        $user->method('getCustomer')->willReturn($customer);

        return $user;
    }

    protected function token(ShopUserInterface $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    protected function request(): Request
    {
        $request = Request::create('/account/delete', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
