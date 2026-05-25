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

class BlockAccountController extends AbstractCustomerSecurityActionController implements BlockAccountControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_block';

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
        $this->verifyCsrfTokenOrThrow($request, self::CSRF_TOKEN_ID);

        $shopUser = $this->loadShopUserOr404($id);

        // Blocking the account also kills any active sessions — leaving them open
        // would mean the customer can keep browsing the shop with the cookie they
        // already have until the cookie expires, defeating the point of the block.
        //
        // The tracker's revokeAll() flushes the unit of work, so the setEnabled(false)
        // staged above is persisted together with the session revocations in a
        // single transaction. We deliberately do not call entityManager->flush()
        // again afterwards — that would be a redundant no-op and would create the
        // illusion that the controller manages persistence itself.
        $shopUser->setEnabled(false);
        $this->sessionTracker->revokeAll($shopUser);

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.blocked', $id);
    }
}
