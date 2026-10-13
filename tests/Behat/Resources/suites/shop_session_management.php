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
                (new Suite('shop_session_management'))
                    ->withContexts(
                        'sylius.behat.context.hook.doctrine_orm',
                        'three_brs.behat.context.hook.security_settings',
                        'three_brs.behat.context.hook.rate_limiter_cache',
                        'sylius.behat.context.setup.channel',
                        'sylius.behat.context.setup.currency',
                        'sylius.behat.context.setup.geographical',
                        'sylius.behat.context.setup.zone',
                        'sylius.behat.context.setup.customer',
                        'sylius.behat.context.transform.shared_storage',
                        'three_brs.behat.context.ui.shop.session_management',
                        'three_brs.behat.context.ui.shop.two_factor_login',
                    )
                    ->withFilter(new TagFilter('@shop&&@session_management&&@ui')),
            ),
    );
