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
                (new Suite('shop_guest_customer_sign_in'))
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
                        'sylius.behat.context.setup.product',
                        'sylius.behat.context.setup.order',
                        'sylius.behat.context.transform.customer',
                        'sylius.behat.context.transform.shared_storage',
                        'three_brs.behat.context.ui.shop.social_login',
                        'three_brs.behat.context.ui.shop.passkey_ceremony',
                        'three_brs.behat.context.ui.shop.guest_cart',
                    )
                    ->withFilter(new TagFilter('@shop&&@guest_customer_sign_in&&@ui')),
            ),
    );
