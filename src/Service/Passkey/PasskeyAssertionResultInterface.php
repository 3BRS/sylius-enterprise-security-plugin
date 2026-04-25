<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Passkey;

interface PasskeyAssertionResultInterface
{
    public function getCredential(): object;

    public function getUser(): object;

    public function isUserVerified(): bool;
}
