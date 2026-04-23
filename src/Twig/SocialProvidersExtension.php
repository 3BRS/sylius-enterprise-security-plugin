<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Twig;

use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthProviderRegistryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SocialProvidersExtension extends AbstractExtension implements SocialProvidersExtensionInterface
{
    public function __construct(
        private OAuthProviderRegistryInterface $registry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('three_brs_social_providers', $this->getSocialProviderNames(...)),
        ];
    }

    /**
     * @return list<string>
     */
    public function getSocialProviderNames(string $group): array
    {
        $providers = $group === 'admin'
            ? $this->registry->getEnabledForAdmin()
            : $this->registry->getEnabledForCustomer();

        return array_map(static fn (OAuthProviderInterface $p) => $p->getName(), $providers);
    }
}
