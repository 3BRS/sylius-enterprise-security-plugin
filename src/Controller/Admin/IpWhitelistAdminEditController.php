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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelistInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\AdminUserIpWhitelistType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;
use Twig\Environment;

class IpWhitelistAdminEditController implements IpWhitelistAdminEditControllerInterface
{
    use FlashHelperTrait;

    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserIpWhitelistRepositoryInterface $whitelistRepository,
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

        $whitelist = $this->whitelistRepository->findOneByAdminUser($admin);

        $formData = [
            'enabled' => $whitelist?->isEnabled() ?? false,
            'cidrs' => $whitelist?->getCidrs() ?? [],
        ];

        $form = $this->formFactory->create(AdminUserIpWhitelistType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $whitelist = $this->upsertWhitelist($admin, $whitelist, (bool) ($data['enabled'] ?? false), $this->normaliseCidrs($data['cidrs'] ?? []));

            $this->entityManager->flush();

            $this->addFlashMessage($request, 'success', 'three_brs.ip_whitelist.admin.saved');

            return new RedirectResponse($this->router->generate('three_brs_admin_ip_whitelist_admins'));
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/IpWhitelist/admin_edit.html.twig',
            [
                'form' => $form->createView(),
                'admin' => $admin,
                'whitelist' => $whitelist,
            ],
        ));
    }

    /**
     * @param list<string> $cidrs
     */
    protected function upsertWhitelist(
        AdminUserInterface $admin,
        ?AdminUserIpWhitelistInterface $existing,
        bool $enabled,
        array $cidrs,
    ): AdminUserIpWhitelistInterface {
        $whitelist = $existing;
        if ($whitelist === null) {
            $whitelist = new AdminUserIpWhitelist();
            $whitelist->setAdminUser($admin);
            $this->entityManager->persist($whitelist);
        }

        $whitelist->setEnabled($enabled);
        $whitelist->setCidrs($cidrs);
        $whitelist->touchUpdatedAt();

        return $whitelist;
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
