<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\CustomerPasswordLoginCheckListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(CustomerPasswordLoginCheckListener::class)]
class CustomerPasswordLoginCheckListenerTest extends TestCase
{
    public function testIgnoresRequestsThatAreNotTheWebLoginCheck(): void
    {
        // json_login / API auth uses a different route — must never be blocked here,
        // even with password login disabled (the plugin leaves the API untouched).
        self::expectNotToPerformAssertions();

        $listener = $this->listener(passwordLoginEnabled: false, route: 'sylius_shop_json_login_check');
        $listener->onCheckPassport($this->passwordEvent($this->createStub(ShopUserInterface::class)));
    }

    public function testAllowsWhenPasswordLoginEnabled(): void
    {
        self::expectNotToPerformAssertions();

        $listener = $this->listener(passwordLoginEnabled: true, route: 'sylius_shop_login_check');
        $listener->onCheckPassport($this->passwordEvent($this->createStub(ShopUserInterface::class)));
    }

    public function testIgnoresNonShopUser(): void
    {
        self::expectNotToPerformAssertions();

        $listener = $this->listener(passwordLoginEnabled: false, route: 'sylius_shop_login_check');
        $listener->onCheckPassport($this->passwordEvent($this->createStub(UserInterface::class)));
    }

    public function testThrowsWhenPasswordLoginDisabledOnTheLoginCheck(): void
    {
        $listener = $this->listener(passwordLoginEnabled: false, route: 'sylius_shop_login_check');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('three_brs.password_login.disabled');

        $listener->onCheckPassport($this->passwordEvent($this->createStub(ShopUserInterface::class)));
    }

    protected function listener(bool $passwordLoginEnabled, string $route): CustomerPasswordLoginCheckListener
    {
        $checker = $this->createStub(PasswordLoginCheckerInterface::class);
        $checker->method('isEnabled')->willReturn($passwordLoginEnabled);

        $request = Request::create('/login_check', 'POST');
        $request->attributes->set('_route', $route);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CustomerPasswordLoginCheckListener($checker, $requestStack);
    }

    protected function passwordEvent(UserInterface $user): CheckPassportEvent
    {
        $passport = new Passport(
            new UserBadge('u', static fn () => $user),
            new PasswordCredentials('plain'),
        );

        return new CheckPassportEvent($this->stubAuthenticator(), $passport);
    }

    protected function stubAuthenticator(): AuthenticatorInterface
    {
        return new class() implements AuthenticatorInterface {
            public function supports(Request $request): ?bool
            {
                return null;
            }

            public function authenticate(Request $request): Passport
            {
                throw new \LogicException('not used');
            }

            public function createToken(Passport $passport, string $firewallName): TokenInterface
            {
                throw new \LogicException('not used');
            }

            public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
            {
                return null;
            }

            public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
            {
                return null;
            }
        };
    }
}
