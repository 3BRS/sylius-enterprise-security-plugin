<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;

class RevokeSessionController extends AbstractCustomerSecurityActionController implements RevokeSessionControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_revoke_session';

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        protected CustomerSessionRepositoryInterface $sessionRepository,
        protected CustomerSessionTrackerInterface $sessionTracker,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
    ) {
        parent::__construct($customerRepository, $csrfTokenManager, $router);
    }

    public function __invoke(Request $request, int $id, int $sessionId): Response
    {
        $csrfFailure = $this->csrfFailureRedirect($request, self::CSRF_TOKEN_ID, $id);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }

        $shopUser = $this->loadShopUserOr404($id);

        // The repo lookup is scoped to (sessionId, shopUser) so a session belonging
        // to another customer cannot be revoked through this admin's URL — that
        // would be an authorization bypass via URL guessing.
        $session = $this->sessionRepository->findByIdAndShopUser($sessionId, $shopUser);
        if ($session === null) {
            throw new NotFoundHttpException();
        }

        $this->sessionTracker->revoke($session);

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.session_revoked', $id);
    }
}
