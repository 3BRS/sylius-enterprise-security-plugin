<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;

interface AdminUserSessionLoginHandlerInterface
{
    public function handle(AdminUserInterface $user, Request $request): void;
}
