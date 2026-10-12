<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractOAuthInitiateController;
use ThreeBRS\EnterpriseSecurityBundle\OAuth\OAuthProviderInterface;

class OAuthInitiateController extends AbstractOAuthInitiateController implements OAuthInitiateControllerInterface
{
    public const STATE_SESSION_KEY = 'three_brs_oauth_state_customer';

    public const INTENT_SESSION_KEY = 'three_brs_oauth_intent_customer';

    protected function isProviderEnabledForScope(OAuthProviderInterface $provider): bool
    {
        return $provider->isEnabledForCustomer();
    }

    protected function getOAuthGroup(): string
    {
        return 'customer';
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
        return 'three_brs_shop_oauth_callback';
    }
}
