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
                (new Suite('shop_magic_link'))
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
                        'three_brs.behat.context.ui.shop.magic_link',
                        // Combinations K1 and K5 from docs/manual-test-plan.md: a magic link
                        // meets the second factor and the lockout, both of which guard the
                        // password form only.
                        'three_brs.behat.context.ui.shop.two_factor_login',
                        'three_brs.behat.context.ui.shop.alternative_sign_in',
                        // A guest cart taken over by the magic-link sign-in.
                        'sylius.behat.context.setup.product',
                        'three_brs.behat.context.ui.shop.guest_cart',
                    )
                    ->withFilter(new TagFilter('@shop&&@magic_link&&@ui')),
            ),
    );
