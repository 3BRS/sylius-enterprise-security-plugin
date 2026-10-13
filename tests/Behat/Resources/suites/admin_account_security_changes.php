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
                (new Suite('admin_account_security_changes'))
                    ->withContexts(
                        'sylius.behat.context.hook.doctrine_orm',
                        'three_brs.behat.context.hook.security_settings',
                        'three_brs.behat.context.hook.rate_limiter_cache',
                        'sylius.behat.context.setup.channel',
                        'sylius.behat.context.setup.currency',
                        'sylius.behat.context.setup.geographical',
                        'sylius.behat.context.setup.zone',
                        'sylius.behat.context.setup.admin_user',
                        'sylius.behat.context.setup.admin_security',
                        'sylius.behat.context.transform.shared_storage',
                        'three_brs.behat.context.ui.admin.account_security_changes',
                        'three_brs.behat.context.ui.admin.social_login',
                        'three_brs.behat.context.ui.admin.two_factor_login',
                        'three_brs.behat.context.ui.admin.two_factor_setup',
                        'three_brs.behat.context.ui.admin.two_factor_enforcement',
                    )
                    ->withFilter(new TagFilter('@admin&&@account_security_changes&&@ui')),
            ),
    );
