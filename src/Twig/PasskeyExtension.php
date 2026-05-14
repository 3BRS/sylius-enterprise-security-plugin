<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PasskeyExtension extends AbstractExtension implements PasskeyExtensionInterface
{
    public function __construct(
        protected bool $customerEnabled,
        protected bool $adminEnabled,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_passkey_enabled', $this->isEnabled(...)),
        ];
    }

    public function isEnabled(string $group): bool
    {
        return $group === 'admin' ? $this->adminEnabled : $this->customerEnabled;
    }
}
