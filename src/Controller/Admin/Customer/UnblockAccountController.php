<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;

#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
class UnblockAccountController extends AbstractCustomerSecurityActionController implements UnblockAccountControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_unblock';

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected CustomerDeletionRequestRepositoryInterface $deletionRequestRepository,
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

        $customer = $this->loadCustomerOr404($id);
        $shopUser = $this->loadShopUserOr404($id);

        // `enabled = false` is the whole of how a scheduled erasure is enforced, so
        // flipping it back here would quietly undo it: the customer signs in again,
        // the administrator believes the account is restored, and the cron anonymises
        // name, e-mail, phone and addresses on the scheduled day regardless. The
        // request has its own screen, where cancelling is recorded against the
        // administrator who did it.
        if ($this->deletionRequestRepository->findActiveForCustomer($customer) !== null) {
            return $this->flashAndRedirect(
                $request,
                'error',
                'three_brs.customer_security.unblock_blocked_by_deletion_request',
                'three_brs_admin_account_deletions',
            );
        }

        $shopUser->setEnabled(true);
        $this->entityManager->flush();

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.unblocked', $id);
    }
}
