<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;

interface CustomerSessionLoginHandlerInterface
{
    public function handle(ShopUserInterface $user, Request $request): void;
}
