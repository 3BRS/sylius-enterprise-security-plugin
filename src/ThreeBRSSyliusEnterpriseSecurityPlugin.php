<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\ThreeBRSSyliusEnterpriseSecurityExtension;

class ThreeBRSSyliusEnterpriseSecurityPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getContainerExtension(): ThreeBRSSyliusEnterpriseSecurityExtension
    {
        return new ThreeBRSSyliusEnterpriseSecurityExtension();
    }
}
