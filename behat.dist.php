<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\TesterOptions;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension;
use FriendsOfBehat\SuiteSettingsExtension\ServiceContainer\SuiteSettingsExtension;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;
use FriendsOfBehat\VariadicExtension\ServiceContainer\VariadicExtension;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;

return (new Config())
    ->import('tests/Behat/Resources/suites.php')
    ->withProfile(
        (new Profile('default'))
            ->withTesterOptions((new TesterOptions())->withErrorReporting(\E_ALL & ~(\E_DEPRECATED | \E_USER_DEPRECATED)))
            ->withExtension(new Extension(MinkExtension::class, [
                'base_url' => 'http://localhost:8081/',
                'default_session' => 'symfony',
                'javascript_session' => 'chrome',
                'sessions' => [
                    'symfony' => [
                        'symfony' => null,
                    ],
                    // Reached only by scenarios tagged @javascript. Everything else keeps
                    // running on the kernel session, which is faster and needs no browser.
                    'chrome' => [
                        'chrome' => [
                            'api_url' => 'http://chrome:9222',
                            'validate_certificate' => false,
                        ],
                    ],
                ],
                'show_auto' => false, // do not automatically open browser on error
            ]))
            ->withExtension(new Extension(ChromeExtension::class))
            ->withExtension(new Extension(SymfonyExtension::class, [
                'bootstrap' => 'tests/Application/config/bootstrap.php',
                'kernel' => [
                    'class' => Kernel::class,
                ],
            ]))
            ->withExtension(new Extension(VariadicExtension::class))
            ->withExtension(new Extension(SuiteSettingsExtension::class, [
                'paths' => ['features'],
            ])),
    );
