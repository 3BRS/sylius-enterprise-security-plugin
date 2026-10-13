<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;

// Declared under its own profile, not `default`: this suite needs the chrome and
// nginx containers, which the CI workflow does not yet start. Run it with
// `vendor/bin/behat -p javascript`. Behat merges the default profile in, so the
// extensions configured there still apply.
return (new Config())
    ->withProfile(
        (new Profile('javascript'))
            ->withSuite(
                (new Suite('shop_passkey_browser'))
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
                        'three_brs.behat.context.ui.shop.passkey_ceremony',
                        'three_brs.behat.context.ui.shop.passkey_browser',
                    )
                    ->withFilter(new TagFilter('@shop&&@passkey_browser&&@javascript')),
            ),
    );
