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
                (new Suite('admin_password_change_notification'))
                    ->withContexts(
                        'sylius.behat.context.hook.doctrine_orm',
                        'three_brs.behat.context.hook.security_settings',
                        'three_brs.behat.context.hook.rate_limiter_cache',
                        'sylius.behat.context.setup.channel',
                        'sylius.behat.context.setup.currency',
                        'sylius.behat.context.setup.geographical',
                        'sylius.behat.context.setup.zone',
                        'sylius.behat.context.setup.admin_security',
                        'sylius.behat.context.setup.admin_user',
                        'sylius.behat.context.transform.shared_storage',
                        'three_brs.behat.context.ui.admin.password_change_notification',
                        'three_brs.behat.context.ui.admin.password_expiration',
                    )
                    ->withFilter(new TagFilter('@admin&&@password_change_notification&&@ui')),
            ),
    );
