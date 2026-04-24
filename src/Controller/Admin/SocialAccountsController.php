<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class SocialAccountsController implements SocialAccountsControllerInterface
{
    public function __construct(
        protected Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/OAuth/accounts.html.twig',
        ));
    }
}
