<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;

class IpBlacklistContext implements Context
{
    public function __construct(
        protected SettingsWriterInterface $settingsWriter,
    ) {
    }

    /**
     * @Given the admin IP blacklist is enabled with global CIDRs :cidrs
     */
    public function adminIpBlacklistIsEnabledWithGlobalCidrs(string $cidrs): void
    {
        $list = $this->splitCidrs($cidrs);
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_blacklist.enabled' => true,
            'ip_blacklist.global_cidrs' => $list,
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given the admin IP blacklist is enabled with no global CIDRs
     */
    public function adminIpBlacklistIsEnabledWithNoGlobalCidrs(): void
    {
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_blacklist.enabled' => true,
            'ip_blacklist.global_cidrs' => [],
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given the admin IP blacklist is disabled
     */
    public function adminIpBlacklistIsDisabled(): void
    {
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_blacklist.enabled' => false,
            'ip_blacklist.global_cidrs' => [],
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @return list<string>
     */
    protected function splitCidrs(string $cidrs): array
    {
        $list = [];
        foreach (preg_split('/\s*,\s*/', $cidrs) ?: [] as $cidr) {
            $cidr = trim($cidr);
            if ($cidr !== '') {
                $list[] = $cidr;
            }
        }

        return $list;
    }
}
