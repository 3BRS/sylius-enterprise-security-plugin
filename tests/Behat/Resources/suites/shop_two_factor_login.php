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
                (new Suite('shop_two_factor_login'))
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
                        'sylius.behat.context.ui.shop.login',
                        'three_brs.behat.context.ui.shop.two_factor_login',
                        'three_brs.behat.context.ui.shop.account_security_changes',
                        'three_brs.behat.context.ui.shop.pending_sign_in',
                    )
                    ->withFilter(new TagFilter('@shop&&@two_factor_login&&@ui')),
            ),
    );
