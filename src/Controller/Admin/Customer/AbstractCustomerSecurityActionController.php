<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin\Customer;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    protected function isCsrfTokenValid(Request $request, string $tokenId): bool
    {
        $token = (string) $request->request->get('_csrf_token', '');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token));
    }

    /**
     * A stale CSRF token here almost always means the session was renewed (a
     * timeout, or a sign-in/out elsewhere in the same browser), not an attack.
     * Returns a redirect-back response with a friendly notice when the token is
     * invalid, or null when it is valid and the action may proceed — so the
     * admin sees a retry-able message instead of a raw 400.
     */
    protected function csrfFailureRedirect(Request $request, string $tokenId, int $customerId): ?Response
    {
        if ($this->isCsrfTokenValid($request, $tokenId)) {
            return null;
        }

        $this->addFlashMessage($request, 'error', 'three_brs.customer_security.session_expired');

        return new RedirectResponse($this->router->generate('sylius_admin_customer_show', ['id' => $customerId]));
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
