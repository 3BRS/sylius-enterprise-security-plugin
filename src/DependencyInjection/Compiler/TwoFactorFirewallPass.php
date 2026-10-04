<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\CancelPendingSignInRequiredHandler;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\PendingSignInCanceller;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAwareAuthenticationSuccessHandler;

/**
 * Registers the services built on scheb's per-firewall two-factor services for the shop and admin
 * firewalls, each only when scheb created its default authentication-required handler, i.e. when
 * the firewall has a `two_factor` block without its own `authentication_required_handler`. An
 * application that does not use two-factor authentication gets none of these. A service the
 * application defines under the same ID is kept.
 */
class TwoFactorFirewallPass implements TwoFactorFirewallPassInterface
{
    public const SHOP_SUCCESS_HANDLER_ID = TwoFactorAwareAuthenticationSuccessHandler::class . '.shop';

    public const PENDING_SIGN_IN_CANCELLER_ID_PREFIX = 'three_brs.two_factor.pending_sign_in_canceller.';

    public const CANCEL_PENDING_SIGN_IN_REQUIRED_HANDLER_ID_PREFIX = 'three_brs.two_factor.cancel_pending_sign_in_required_handler.';

    protected const REQUIRED_HANDLER_ID_PREFIX = 'security.authentication.authentication_required_handler.two_factor.';

    protected const SHOP_FIREWALL = 'shop';

    protected const FIREWALLS = [
        'shop' => [
            'sign_in_routes' => ['sylius_shop_login'],
            'two_factor_routes' => ['three_brs_shop_two_factor_recovery_challenge'],
        ],
        'admin' => [
            'sign_in_routes' => ['sylius_admin_login'],
            'two_factor_routes' => ['three_brs_admin_two_factor_recovery_challenge'],
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (static::FIREWALLS as $firewall => $routes) {
            $requiredHandlerId = static::REQUIRED_HANDLER_ID_PREFIX . $firewall;
            if (!$container->has($requiredHandlerId)) {
                continue;
            }

            if ($firewall === static::SHOP_FIREWALL) {
                $this->registerShopSuccessHandler($container, $requiredHandlerId);
            }

            $this->registerPendingSignInCancellation(
                $container,
                $firewall,
                $requiredHandlerId,
                $routes['sign_in_routes'],
                $routes['two_factor_routes'],
            );
        }
    }

    protected function registerShopSuccessHandler(ContainerBuilder $container, string $requiredHandlerId): void
    {
        if ($container->has(static::SHOP_SUCCESS_HANDLER_ID)) {
            return;
        }

        $container->setDefinition(
            static::SHOP_SUCCESS_HANDLER_ID,
            (new Definition(TwoFactorAwareAuthenticationSuccessHandler::class))
                ->setArguments([
                    '$twoFactorAuthenticationRequiredHandler' => new Reference($requiredHandlerId),
                    '$defaultSuccessHandler' => new Reference('sylius.authentication.success_handler'),
                ])
                ->setPublic(true),
        );
    }

    /**
     * @param list<string> $signInRoutes
     * @param list<string> $twoFactorRoutes
     */
    protected function registerPendingSignInCancellation(
        ContainerBuilder $container,
        string $firewall,
        string $requiredHandlerId,
        array $signInRoutes,
        array $twoFactorRoutes,
    ): void {
        $cancellerId = static::PENDING_SIGN_IN_CANCELLER_ID_PREFIX . $firewall;
        if (!$container->has($cancellerId)) {
            $container->setDefinition(
                $cancellerId,
                (new Definition(PendingSignInCanceller::class))
                    ->setArguments([
                        '$tokenStorage' => new Reference('security.token_storage'),
                        '$twoFactorAccessDecider' => new Reference('scheb_two_factor.security.access.access_decider'),
                        '$twoFactorFirewallConfig' => new Reference('security.firewall_config.two_factor.' . $firewall),
                        '$signInRoutes' => $signInRoutes,
                        '$twoFactorRoutes' => $twoFactorRoutes,
                    ])
                    ->addTag('kernel.event_subscriber'),
            );
        }

        $handlerId = static::CANCEL_PENDING_SIGN_IN_REQUIRED_HANDLER_ID_PREFIX . $firewall;
        if (!$container->has($handlerId)) {
            $container->setDefinition(
                $handlerId,
                (new Definition(CancelPendingSignInRequiredHandler::class))
                    ->setDecoratedService($requiredHandlerId)
                    ->setArguments([
                        '$inner' => new Reference($handlerId . '.inner'),
                        '$pendingSignInCanceller' => new Reference($cancellerId),
                    ]),
            );
        }
    }
}
