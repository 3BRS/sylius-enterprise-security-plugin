<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class SocialAccountsController implements SocialAccountsControllerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/OAuth/accounts.html.twig',
        ));
    }
}
