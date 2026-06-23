<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\PasswordLoginCheckerInterface;

/**
 * Backstop for the vanilla email + password registration: when password login is disabled
 * for customers, a direct POST to the registration route is refused (the form itself is
 * hidden in the template, but this stops a hand-crafted submission from creating an account).
 * Only the web shop registration route is gated — the API is left untouched.
 */
class RegistrationBlockListener implements RegistrationBlockListenerInterface
{
    protected const REGISTER_ROUTE = 'sylius_shop_register';

    public function __construct(
        protected PasswordLoginCheckerInterface $passwordLoginChecker,
        protected RouterInterface $router,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== static::REGISTER_ROUTE || !$request->isMethod('POST')) {
            return;
        }

        if ($this->passwordLoginChecker->isEnabled(SettingsScope::CUSTOMER)) {
            return;
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'three_brs.password_login.registration_disabled');
        }

        $event->setResponse(new RedirectResponse($this->router->generate('sylius_shop_login')));
    }
}
