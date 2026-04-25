<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class PasskeyDeleteController implements PasskeyDeleteControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TokenStorageInterface $tokenStorage,
        protected LastAuthMethodGuardInterface $lastAuthMethodGuard,
        protected RouterInterface $router,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new AccessDeniedHttpException();
        }

        $credential = $this->entityManager->getRepository(CustomerPasskeyCredential::class)->find($id);
        if ($credential === null || $credential->getShopUser()->getId() !== $user->getId()) {
            throw new NotFoundHttpException();
        }

        if (!$this->lastAuthMethodGuard->canRemovePasskeyForShopUser($user)) {
            $this->addFlashMessage($request, 'error', 'three_brs.ui.passkey.cannot_remove_last_auth_method');

            return new RedirectResponse($this->router->generate('three_brs_shop_passkey_index'));
        }

        $this->entityManager->remove($credential);
        $this->entityManager->flush();

        $this->addFlashMessage($request, 'success', 'three_brs.ui.passkey.removed');

        return new RedirectResponse($this->router->generate('three_brs_shop_passkey_index'));
    }
}
