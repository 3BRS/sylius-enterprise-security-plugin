<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpBlacklistRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpBlacklist\IpBlacklistCheckerInterface;
use Twig\Environment;

class IpBlacklistAdminsController implements IpBlacklistAdminsControllerInterface
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserIpBlacklistRepositoryInterface $blacklistRepository,
        protected IpBlacklistCheckerInterface $checker,
        protected Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $admins = $this->adminUserRepository->findBy([], ['email' => 'ASC']);

        $rows = [];
        foreach ($admins as $admin) {
            $blacklist = $this->blacklistRepository->findOneByAdminUser($admin);
            $rows[] = [
                'admin' => $admin,
                'blacklist' => $blacklist,
            ];
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/IpBlacklist/admins.html.twig',
            [
                'rows' => $rows,
                'featureEnabled' => $this->checker->isFeatureEnabled(),
                'globalCidrs' => $this->checker->getGlobalCidrs(),
            ],
        ));
    }
}
