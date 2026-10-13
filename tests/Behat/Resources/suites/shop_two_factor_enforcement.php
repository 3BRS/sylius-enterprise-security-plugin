<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('shop_two_factor_enforcement'))
                    ->withContexts(
                        'sylius.behat.context.hook.doctrine_orm',
                        'three_brs.behat.context.hook.security_settings',
                        'three_brs.behat.context.hook.rate_limiter_cache',
                        'sylius.behat.context.setup.channel',
                        'sylius.behat.context.setup.currency',
                        'sylius.behat.context.setup.geographical',
                        'sylius.behat.context.setup.zone',
                        'sylius.behat.context.setup.customer',
                        'sylius.behat.context.setup.shop_security',
                        'sylius.behat.context.transform.shared_storage',
                        'three_brs.behat.context.ui.shop.two_factor_setup',
                        'three_brs.behat.context.ui.shop.two_factor_enforcement',
                        // Combination K3: enforcement with password login switched off, where
                        // the only way in is the very path enforcement redirects away from.
                        'three_brs.behat.context.ui.shop.password_login',
                        'three_brs.behat.context.ui.shop.magic_link',
                    )
                    ->withFilter(new TagFilter('@shop&&@two_factor_enforcement&&@ui')),
            ),
    );
