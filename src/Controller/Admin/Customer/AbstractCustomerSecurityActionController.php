<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use ThreeBRS\EnterpriseSecurityBundle\Controller\FlashHelperTrait;

/**
 * Shared shell for the five admin customer-security POST handlers: CSRF check,
 * customer + shop-user lookup, flash + redirect back to the customer detail page.
 *
 * Concrete controllers only fill in the mutation step (force change flag,
 * setEnabled toggle, session revoke …) and the success flash key.
 *
 * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
 */
abstract class AbstractCustomerSecurityActionController
{
    use FlashHelperTrait;

    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected RouterInterface $router,
    ) {
    }

    protected function verifyCsrfTokenOrThrow(Request $request, string $tokenId): void
    {
        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
    }

    protected function loadShopUserOr404(int $customerId): ShopUserInterface
    {
        $customer = $this->customerRepository->find($customerId);
        if (!$customer instanceof CustomerInterface) {
            throw new NotFoundHttpException();
        }

        $shopUser = $customer->getUser();
        if (!$shopUser instanceof ShopUserInterface) {
            throw new NotFoundHttpException();
        }

        return $shopUser;
    }

    protected function flashAndRedirectToDetail(Request $request, string $flashKey, int $customerId): Response
    {
        $this->addFlashMessage($request, 'success', $flashKey);

        return new RedirectResponse($this->router->generate('sylius_admin_customer_show', ['id' => $customerId]));
    }
}
