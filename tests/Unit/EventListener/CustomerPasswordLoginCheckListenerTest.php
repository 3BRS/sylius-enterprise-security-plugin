<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use ThreeBRS\EnterpriseSecurityBundle\PasswordLoginControl\PasswordLoginPreferenceRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener\CustomerPasswordLoginCheckListener;

#[CoversClass(CustomerPasswordLoginCheckListener::class)]
class CustomerPasswordLoginCheckListenerTest extends TestCase
{
    public function testThrowsForShopUserWithPasswordLoginDisabledInCustomerScope(): void
    {
        $featureToggle = $this->createMock(FeatureToggleInterface::class);
        $featureToggle->expects(self::once())
            ->method('isEnabled')
            ->with('password_login_control', SettingsScope::CUSTOMER)
            ->willReturn(true);

        $repository = $this->createStub(PasswordLoginPreferenceRepositoryInterface::class);
        $repository->method('isPasswordLoginAllowedForUser')->willReturn(false);

        $listener = new CustomerPasswordLoginCheckListener($repository, $featureToggle);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('three_brs.password_login_control.disabled_for_user');

        $listener->onCheckPassport($this->passwordLoginEvent($this->createStub(ShopUserInterface::class)));
    }

    public function testIgnoresAdminUserOnTheCustomerFirewall(): void
    {
        // Wrong user type for this listener — settings and the preference must not be consulted.
        $featureToggle = $this->createMock(FeatureToggleInterface::class);
        $featureToggle->expects(self::never())->method('isEnabled');

        $repository = $this->createMock(PasswordLoginPreferenceRepositoryInterface::class);
        $repository->expects(self::never())->method('isPasswordLoginAllowedForUser');

        $listener = new CustomerPasswordLoginCheckListener($repository, $featureToggle);

        $listener->onCheckPassport($this->passwordLoginEvent($this->createStub(AdminUserInterface::class)));
    }

    public function testAllowsShopUserWhenPreferenceAllows(): void
    {
        $featureToggle = $this->createStub(FeatureToggleInterface::class);
        $featureToggle->method('isEnabled')->willReturn(true);

        $repository = $this->createMock(PasswordLoginPreferenceRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('isPasswordLoginAllowedForUser')
            ->willReturn(true);

        $listener = new CustomerPasswordLoginCheckListener($repository, $featureToggle);

        $listener->onCheckPassport($this->passwordLoginEvent($this->createStub(ShopUserInterface::class)));
    }

    protected function passwordLoginEvent(UserInterface $user): CheckPassportEvent
    {
        $passport = new Passport(
            new UserBadge('u', static fn (): UserInterface => $user),
            new PasswordCredentials('plain'),
        );

        return new CheckPassportEvent($this->createStub(AuthenticatorInterface::class), $passport);
    }
}
