<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PasswordLoginControlExtension extends AbstractExtension implements PasswordLoginControlExtensionInterface
{
    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_password_login_enabled', $this->isEnabled(...)),
        ];
    }

    public function isEnabled(string $scope): bool
    {
        return $this->passwordLoginChecker->isEnabled(SettingsScope::from($scope));
    }
}
