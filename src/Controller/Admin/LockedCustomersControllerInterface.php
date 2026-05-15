<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;

interface LockedCustomersControllerInterface
{
    public function __invoke(): Response;
}
