<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;

class ForcePasswordResetController extends AbstractCustomerSecurityActionController implements ForcePasswordResetControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_force_password_reset';

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
        $this->verifyCsrfTokenOrThrow($request, self::CSRF_TOKEN_ID);

        $shopUser = $this->loadShopUserOr404($id);
        if (!$shopUser instanceof PasswordExpirationShopUserInterface) {
            throw new NotFoundHttpException();
        }

        $shopUser->setForcePasswordChange(true);
        $this->entityManager->flush();

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.password_reset_forced', $id);
    }
}
