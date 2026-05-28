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

class UnblockAccountController extends AbstractCustomerSecurityActionController implements UnblockAccountControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_unblock';

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
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
        $shopUser->setEnabled(true);
        $this->entityManager->flush();

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.unblocked', $id);
    }
}
