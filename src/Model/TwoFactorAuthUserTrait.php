<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Model;

use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

trait TwoFactorAuthUserTrait
{
    #[ORM\Column(name: 'totp_secret', type: 'string', nullable: true)]
    protected ?string $totpSecret = null;

    #[ORM\Column(name: 'two_factor_enabled', type: 'boolean', options: ['default' => false])]
    protected bool $twoFactorEnabled = false;

    #[ORM\Column(name: 'trusted_token_version', type: 'integer', options: ['default' => 0])]
    protected int $trustedTokenVersion = 0;

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $enabled): void
    {
        $this->twoFactorEnabled = $enabled;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->twoFactorEnabled && $this->totpSecret !== null;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return (string) $this->getEmail();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if ($this->totpSecret === null) {
            return null;
        }

        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getTrustedTokenVersion(): int
    {
        return $this->trustedTokenVersion;
    }

    public function bumpTrustedTokenVersion(): void
    {
        ++$this->trustedTokenVersion;
    }
}
