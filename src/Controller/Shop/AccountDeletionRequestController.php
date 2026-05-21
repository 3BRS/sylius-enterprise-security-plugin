<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractAccountDeletionRequestController;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\AccountDeletionRequestType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;
use Twig\Environment;

class AccountDeletionRequestController extends AbstractAccountDeletionRequestController implements AccountDeletionRequestControllerInterface
{
    public function __construct(
        protected FormFactoryInterface $formFactory,
        protected CustomerDeletionServiceInterface $deletionService,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $passwordHasher, $router, $twig, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function hasDeletableSubject(UserInterface $user): bool
    {
        \assert($user instanceof ShopUserInterface);

        return $user->getCustomer() instanceof CustomerInterface;
    }

    protected function createDeletionRequestForm(): FormInterface
    {
        return $this->formFactory->create(AccountDeletionRequestType::class);
    }

    protected function dispatchDeletionRequest(UserInterface $user): void
    {
        \assert($user instanceof ShopUserInterface);

        $customer = $user->getCustomer();
        \assert($customer instanceof CustomerInterface);

        $this->deletionService->requestDeletion($customer);
    }

    protected function getRequestFormUrl(): string
    {
        return $this->router->generate('three_brs_shop_account_deletion_request');
    }

    protected function getPostDeletionUrl(): string
    {
        return $this->router->generate('sylius_shop_homepage');
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/AccountDeletion/request.html.twig';
    }
}
