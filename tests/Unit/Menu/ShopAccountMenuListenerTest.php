<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Menu;

use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderRegistryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\FeatureToggleInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Menu\ShopAccountMenuListener;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[CoversClass(ShopAccountMenuListener::class)]
class ShopAccountMenuListenerTest extends TestCase
{
    /**
     * The entry has to follow the same registry the sign-in page and the account
     * page read. Naming the providers one by one left the third one out, so a store
     * signing customers in through Microsoft alone offered no way to manage the link.
     */
    public function testTheSocialAccountsEntryFollowsWhicheverProviderIsEnabled(): void
    {
        $menu = $this->makeMenu();

        $this->makeListener(['microsoft'])->addSocialAccountsItem(new MenuBuilderEvent(new MenuFactory(), $menu));

        self::assertNotNull($menu->getChild('social_accounts'));
    }

    public function testNoSocialAccountsEntryWhileEveryProviderIsOff(): void
    {
        $menu = $this->makeMenu();

        $this->makeListener([])->addSocialAccountsItem(new MenuBuilderEvent(new MenuFactory(), $menu));

        self::assertNull($menu->getChild('social_accounts'));
    }

    protected function makeMenu(): MenuItem
    {
        $factory = new MenuFactory();

        return $factory->createItem('root');
    }

    /**
     * @param list<string> $enabledProviders
     */
    protected function makeListener(array $enabledProviders): ShopAccountMenuListener
    {
        $providers = array_map(function (string $name): OAuthProviderInterface {
            $provider = $this->createStub(OAuthProviderInterface::class);
            $provider->method('getName')->willReturn($name);

            return $provider;
        }, $enabledProviders);

        $registry = $this->createStub(OAuthProviderRegistryInterface::class);
        $registry->method('getEnabledForCustomer')->willReturn($providers);

        return new ShopAccountMenuListener(
            $this->createStub(FeatureToggleInterface::class),
            $this->createStub(TokenStorageInterface::class),
            $this->createStub(RouterInterface::class),
            $this->createStub(PasswordLoginCheckerInterface::class),
            $registry,
        );
    }
}
