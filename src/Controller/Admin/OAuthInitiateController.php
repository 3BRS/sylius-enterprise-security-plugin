<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractOAuthInitiateController;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;

class OAuthInitiateController extends AbstractOAuthInitiateController implements OAuthInitiateControllerInterface
{
    public const STATE_SESSION_KEY = 'three_brs_oauth_state_admin';

    public const INTENT_SESSION_KEY = 'three_brs_oauth_intent_admin';

    protected function isProviderEnabledForScope(OAuthProviderInterface $provider): bool
    {
        return $provider->isEnabledForAdmin();
    }

    protected function getOAuthGroup(): string
    {
        return 'admin';
    }

    protected function getStateSessionKey(): string
    {
        return self::STATE_SESSION_KEY;
    }

    protected function getIntentSessionKey(): string
    {
        return self::INTENT_SESSION_KEY;
    }

    protected function getCallbackRouteName(): string
    {
        return 'three_brs_admin_oauth_callback';
    }
}
