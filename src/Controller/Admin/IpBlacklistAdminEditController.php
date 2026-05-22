<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\FlashHelperTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklistInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\AdminUserIpBlacklistType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpBlacklistRepositoryInterface;
use Twig\Environment;

class IpBlacklistAdminEditController implements IpBlacklistAdminEditControllerInterface
{
    use FlashHelperTrait;

    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserIpBlacklistRepositoryInterface $blacklistRepository,
        protected EntityManagerInterface $entityManager,
        protected FormFactoryInterface $formFactory,
        protected RouterInterface $router,
        protected Environment $twig,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $admin = $this->adminUserRepository->find($id);
        if (!$admin instanceof AdminUserInterface) {
            throw new NotFoundHttpException();
        }

        $blacklist = $this->blacklistRepository->findOneByAdminUser($admin);

        $formData = [
            'enabled' => $blacklist?->isEnabled() ?? false,
            'cidrs' => $blacklist?->getCidrs() ?? [],
        ];

        $form = $this->formFactory->create(AdminUserIpBlacklistType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $blacklist = $this->upsertBlacklist($admin, $blacklist, (bool) ($data['enabled'] ?? false), $this->normaliseCidrs($data['cidrs'] ?? []));

            $this->entityManager->flush();

            $this->addFlashMessage($request, 'success', 'three_brs.ip_blacklist.admin.saved');

            return new RedirectResponse($this->router->generate('three_brs_admin_ip_blacklist_admins'));
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/IpBlacklist/admin_edit.html.twig',
            [
                'form' => $form->createView(),
                'admin' => $admin,
                'blacklist' => $blacklist,
            ],
        ));
    }

    /**
     * @param list<string> $cidrs
     */
    protected function upsertBlacklist(
        AdminUserInterface $admin,
        ?AdminUserIpBlacklistInterface $existing,
        bool $enabled,
        array $cidrs,
    ): AdminUserIpBlacklistInterface {
        $blacklist = $existing;
        if ($blacklist === null) {
            $blacklist = new AdminUserIpBlacklist();
            $blacklist->setAdminUser($admin);
            $this->entityManager->persist($blacklist);
        }

        $blacklist->setEnabled($enabled);
        $blacklist->setCidrs($cidrs);
        $blacklist->touchUpdatedAt();

        return $blacklist;
    }

    /**
     * @return list<string>
     */
    protected function normaliseCidrs(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $cidrs = [];
        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                $cidrs[] = $entry;
            }
        }

        return $cidrs;
    }
}
