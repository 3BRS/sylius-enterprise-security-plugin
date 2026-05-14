<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use Twig\Environment;

class PasskeyController implements PasskeyControllerInterface
{
    public function __construct(
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected TokenStorageInterface $tokenStorage,
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUserInterface) {
            throw new AccessDeniedHttpException();
        }

        $credentials = $this->credentialRepository->findAllByAdminUser($user);

        return new Response($this->twig->render('@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Passkey/index.html.twig', [
            'credentials' => $credentials,
        ]));
    }
}
