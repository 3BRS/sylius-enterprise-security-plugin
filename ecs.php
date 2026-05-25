<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/ecs.php',
    ]);

    $ecsConfig->import('vendor/sylius-labs/coding-standard/ecs.php');

    $ecsConfig->skip([
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/tests/Application/var/',
        // packages/enterprise-security-bundle has its own ECS config (PSR-12 + common + strict).
        // The bundle is owned by `make bundle-ecs` / `make bundle-fix`; plugin's ECS must not
        // touch it, otherwise the two rule sets fight on the same files.
        __DIR__ . '/packages/',
        VisibilityRequiredFixer::class => ['*Spec.php'],
    ]);
};
