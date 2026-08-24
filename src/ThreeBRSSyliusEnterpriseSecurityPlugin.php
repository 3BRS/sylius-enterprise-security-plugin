<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\DependencyInjection\ThreeBRSSyliusEnterpriseSecurityExtension;

class ThreeBRSSyliusEnterpriseSecurityPlugin extends Bundle
{
    use SyliusPluginTrait;

    /**
     * Config, templates, translations and assets live at the package root, which is
     * the layout the Sylius plugin skeleton ships and where Symfony looks once the
     * bundle says its path is the root rather than src/.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ThreeBRSSyliusEnterpriseSecurityExtension
    {
        return new ThreeBRSSyliusEnterpriseSecurityExtension();
    }
}
