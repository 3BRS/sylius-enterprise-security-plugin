<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class TwoFactorRecoveryCodesController implements TwoFactorRecoveryCodesControllerInterface
{
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected RouterInterface $router,
        protected Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ShopUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        $session = $request->getSession();
        $codes = $session->get(TwoFactorSetupController::SESSION_PLAIN_RECOVERY_CODES);
        if (!is_array($codes) || $codes === []) {
            return new RedirectResponse($this->router->generate('sylius_shop_account_dashboard'));
        }

        $session->remove(TwoFactorSetupController::SESSION_PLAIN_RECOVERY_CODES);

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/recovery_codes.html.twig',
            ['codes' => $codes],
        ));
    }
}
