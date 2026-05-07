<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\AccountDeletionRequestType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;
use Twig\Environment;

class AccountDeletionRequestController implements AccountDeletionRequestControllerInterface
{
    use FlashHelperTrait;

    public function __construct(
        protected FormFactoryInterface $formFactory,
        protected CustomerDeletionServiceInterface $deletionService,
        protected UserPasswordHasherInterface $passwordHasher,
        protected TokenStorageInterface $tokenStorage,
        protected RouterInterface $router,
        protected Environment $twig,
        protected bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }

        $shopUser = $this->getShopUser();
        $customer = $shopUser->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            throw new NotFoundHttpException();
        }

        $form = $this->formFactory->create(AccountDeletionRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $providedPassword = (string) $form->get('currentPassword')->getData();
            if (!$this->passwordHasher->isPasswordValid($shopUser, $providedPassword)) {
                $this->addFlashMessage($request, 'error', 'three_brs.account_deletion.invalid_password');

                return new RedirectResponse($this->router->generate('three_brs_shop_account_deletion_request'));
            }

            $this->deletionService->requestDeletion($customer);
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            $this->addFlashMessage($request, 'success', 'three_brs.account_deletion.requested');

            return new RedirectResponse($this->router->generate('sylius_shop_homepage'));
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/AccountDeletion/request.html.twig',
            ['form' => $form->createView()],
        ));
    }

    protected function getShopUser(): ShopUserInterface
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw new NotFoundHttpException();
        }

        return $user;
    }
}
