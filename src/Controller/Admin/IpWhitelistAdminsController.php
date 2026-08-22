<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\IpWhitelist\IpWhitelistCheckerInterface;
use Twig\Environment;

#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
class IpWhitelistAdminsController implements IpWhitelistAdminsControllerInterface
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserIpWhitelistRepositoryInterface $whitelistRepository,
        protected IpWhitelistCheckerInterface $checker,
        protected Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $admins = $this->adminUserRepository->findBy([], ['email' => 'ASC']);

        $rows = [];
        foreach ($admins as $admin) {
            $whitelist = $this->whitelistRepository->findOneByAdminUser($admin);
            $rows[] = [
                'admin' => $admin,
                'whitelist' => $whitelist,
            ];
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/IpWhitelist/admins.html.twig',
            [
                'rows' => $rows,
                'featureEnabled' => $this->checker->isFeatureEnabled(),
                'globalCidrs' => $this->checker->getGlobalCidrs(),
            ],
        ));
    }
}
