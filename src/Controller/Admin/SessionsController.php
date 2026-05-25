<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\AbstractSessionsListController;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentParserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use Twig\Environment;

class SessionsController extends AbstractSessionsListController implements SessionsControllerInterface
{
    public function __construct(
        protected AdminUserSessionRepositoryInterface $repository,
        TokenStorageInterface $tokenStorage,
        UserAgentParserInterface $userAgentParser,
        Environment $twig,
        bool $enabled,
    ) {
        parent::__construct($tokenStorage, $userAgentParser, $twig, $enabled);
    }

    protected function isAcceptableUser(UserInterface $user): bool
    {
        return $user instanceof AdminUserInterface;
    }

    protected function findActiveSessionsForUser(UserInterface $user): iterable
    {
        \assert($user instanceof AdminUserInterface);

        return $this->repository->findActiveForAdminUser($user);
    }

    protected function getTemplate(): string
    {
        return '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/Sessions/index.html.twig';
    }
}
