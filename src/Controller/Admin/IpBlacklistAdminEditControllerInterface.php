<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface IpBlacklistAdminEditControllerInterface
{
    public function __invoke(Request $request, int $id): Response;
}
