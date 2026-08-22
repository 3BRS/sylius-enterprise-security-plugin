<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
class ForcePasswordResetController extends AbstractCustomerSecurityActionController implements ForcePasswordResetControllerInterface
{
    protected const CSRF_TOKEN_ID = 'three_brs_customer_force_password_reset';

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        RouterInterface $router,
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
    ) {
        parent::__construct($customerRepository, $csrfTokenManager, $router);
    }

    public function __invoke(Request $request, int $id): Response
    {
        $csrfFailure = $this->csrfFailureRedirect($request, self::CSRF_TOKEN_ID, $id);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }

        // Forcing a password reset is pointless when password login is disabled for customers.
        if (!$this->passwordLoginChecker->isEnabled(SettingsScope::CUSTOMER)) {
            $this->addFlashMessage($request, 'error', 'three_brs.customer_security.password_login_disabled');

            return new RedirectResponse($this->router->generate('sylius_admin_customer_show', ['id' => $id]));
        }

        $shopUser = $this->loadShopUserOr404($id);
        if (!$shopUser instanceof PasswordExpirationShopUserInterface) {
            throw new NotFoundHttpException();
        }

        $shopUser->setForcePasswordChange(true);
        $this->entityManager->flush();

        return $this->flashAndRedirectToDetail($request, 'three_brs.customer_security.password_reset_forced', $id);
    }
}
