<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

class RevokeAllSessionsController extends AbstractCustomerSecurityActionController implements RevokeAllSessionsControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_revoke_all_sessions';

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        protected CustomerSessionTrackerInterface $sessionTracker,
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
        $this->sessionTracker->revokeAll($shopUser);

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.sessions_revoked', $id);
    }
}
