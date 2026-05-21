<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSessionsListController;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentParserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use Twig\Environment;

class SessionsController extends AbstractSessionsListController implements SessionsControllerInterface
{
    public function __construct(
        protected CustomerSessionRepositoryInterface $repository,
        TokenStorageInterface $tokenStorage,
        UserAgentParserInterface $userAgentParser,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $userAgentParser, $twig, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof ShopUserInterface;
    }

    protected function findActiveSessionsForUser(UserInterface $user): iterable
    {
        \assert($user instanceof ShopUserInterface);

        return $this->repository->findActiveForShopUser($user);
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/Sessions/index.html.twig';
    }
}
