<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthUserInfoInterface;

interface ShopSocialLoginHandlerInterface
{
    public function findExistingLinkUser(OAuthUserInfoInterface $info): ?ShopUserInterface;

    public function findUserByEmail(string $email): ?ShopUserInterface;

    public function canAutoRegister(OAuthUserInfoInterface $info): bool;

    public function registerAndLink(OAuthUserInfoInterface $info): ShopUserInterface;

    public function linkExistingUser(ShopUserInterface $user, OAuthUserInfoInterface $info): void;

    public function touchLastUsed(ShopUserInterface $user, OAuthUserInfoInterface $info): void;
}
