<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MagicLinkExtension extends AbstractExtension implements MagicLinkExtensionInterface
{
    public function __construct(
        private bool $customerEnabled,
        private bool $adminEnabled,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_magic_link_enabled', $this->isEnabled(...)),
        ];
    }

    public function isEnabled(string $group): bool
    {
        return $group === 'admin' ? $this->adminEnabled : $this->customerEnabled;
    }
}
