<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface SocialAccountUnlinkControllerInterface
{
    public function __invoke(Request $request, string $provider): Response;
}
