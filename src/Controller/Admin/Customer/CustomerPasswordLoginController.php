<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreference;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\LastAuthMethodGuardInterface;

class CustomerPasswordLoginController extends AbstractCustomerSecurityActionController implements CustomerPasswordLoginControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_password_login';

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        protected CustomerLoginPreferenceRepositoryInterface $preferenceRepository,
        protected LastAuthMethodGuardInterface $guard,
        protected EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
    ) {
        parent::__construct($customerRepository, $csrfTokenManager, $router);
    }

    public function __invoke(Request $request, int $id): Response
    {
        $csrfFailure = $this->csrfFailureRedirect($request, self::CSRF_TOKEN_ID, $id);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }

        $shopUser = $this->loadShopUserOr404($id);
        $allowed = $request->request->getBoolean('allowed');

        if (!$allowed && !$this->guard->canDisablePasswordLoginForShopUser($shopUser)) {
            $this->addFlashMessage($request, 'error', 'three_brs.password_login_control.customer.cannot_disable_last_method');

            return new RedirectResponse($this->router->generate('sylius_admin_customer_show', ['id' => $id]));
        }

        $preference = $this->preferenceRepository->findOneByShopUser($shopUser);
        if ($preference === null) {
            $preference = new CustomerLoginPreference();
            $preference->setShopUser($shopUser);
            $this->entityManager->persist($preference);
        }
        $preference->setPasswordLoginAllowed($allowed);
        $this->entityManager->flush();

        return $this->flashAndRedirectToDetail(
            $request,
            $allowed ? 'three_brs.password_login_control.customer.enabled' : 'three_brs.password_login_control.customer.disabled',
            $id,
        );
    }
}
