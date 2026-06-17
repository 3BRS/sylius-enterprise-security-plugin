<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractMagicLinkVerifyController;
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkTokenInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\CustomerMagicLinkTokenVerifierInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionLoginHandlerInterface;

class MagicLinkVerifyController extends AbstractMagicLinkVerifyController implements MagicLinkVerifyControllerInterface
{
    public function __construct(
        CustomerMagicLinkTokenVerifierInterface $verifier,
        protected EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        ClockInterface $clock,
        LoggerInterface $logger,
        protected CustomerSessionLoginHandlerInterface $sessionLoginHandler,
        bool $enabled,
    ) {
        parent::__construct(
            $verifier,
            $tokenStorage,
            $router,
            $clock,
            $logger,
            $enabled,
        );
    }

    protected function isFullyAuthenticatedUser(?TokenInterface $token): bool
    {
        return $token !== null &&
            !$token instanceof TwoFactorTokenInterface &&
            $token->getUser() instanceof ShopUserInterface;
    }

    protected function getUserFromMagicLink(MagicLinkRecordInterface $magicLink): UserInterface
    {
        \assert($magicLink instanceof CustomerMagicLinkTokenInterface);

        return $magicLink->getShopUser();
    }

    protected function commitMagicLinkUsage(MagicLinkRecordInterface $magicLink): void
    {
        $this->entityManager->flush();
    }

    protected function getFirewallName(): string
    {
        return 'shop';
    }

    protected function getDefaultRedirectUrl(): string
    {
        return $this->router->generate('sylius_shop_account_dashboard');
    }

    protected function getMagicLinkRequestUrl(): string
    {
        return $this->router->generate('three_brs_shop_magic_link_request');
    }

    protected function getLogChannel(): string
    {
        return 'three_brs.magic_link.shop';
    }

    protected function handlePostLogin(UserInterface $user, Request $request): void
    {
        if ($user instanceof ShopUserInterface) {
            $this->sessionLoginHandler->handle($user, $request);
        }
    }
}
