<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * The two-factor actions send a sign-in that is not full, e.g. one restored from a remember-me cookie,
 * to the sign-in page. The two-factor page is saved as the firewall's target path, so the user returns
 * to it after signing in.
 */
trait TwoFactorPageTargetPathTrait
{
    use TargetPathTrait;

    public function __invoke(Request $request): Response
    {
        $response = parent::__invoke($request);

        $token = $this->tokenStorage->getToken();
        if (
            $token?->getUser() !== null &&
            !$this->isFullSignIn($token) &&
            $request->hasSession() &&
            $response instanceof RedirectResponse &&
            $response->getTargetUrl() === $this->getLoginUrl()
        ) {
            $this->saveTargetPath($request->getSession(), $this->getFirewallName(), $this->getTwoFactorPageUrl());
        }

        return $response;
    }

    abstract protected function getFirewallName(): string;

    abstract protected function getTwoFactorPageUrl(): string;
}
