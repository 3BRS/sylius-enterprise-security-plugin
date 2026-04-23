<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\AdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\OAuthUserInfoInterface;

interface AdminSocialLoginHandlerInterface
{
    public function findExistingLinkUser(OAuthUserInfoInterface $info): ?AdminUserInterface;

    public function findUserByEmail(string $email): ?AdminUserInterface;

    public function canAutoRegister(OAuthUserInfoInterface $info): bool;

    public function registerAndLink(OAuthUserInfoInterface $info): AdminUserInterface;

    public function linkExistingUser(AdminUserInterface $user, OAuthUserInfoInterface $info): void;

    public function touchLastUsed(AdminUserInterface $user, OAuthUserInfoInterface $info): void;
}
