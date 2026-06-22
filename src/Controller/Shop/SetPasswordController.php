<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\SetPasswordType;
use Twig\Environment;

class SetPasswordController implements SetPasswordControllerInterface
{
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected UserPasswordHasherInterface $passwordHasher,
        protected EntityManagerInterface $entityManager,
        protected RouterInterface $router,
        protected Environment $twig,
        protected FormFactoryInterface $formFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof ShopUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        // Users who already have a password must use the standard change-password
        // flow, which requires confirming the current password.
        if (!$this->isPasswordless($user)) {
            return new RedirectResponse($this->router->generate('sylius_shop_account_change_password'));
        }

        $form = $this->formFactory->create(SetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{newPassword: string} $data */
            $data = $form->getData();
            $newPassword = $data['newPassword'];

            // Set the hashed password on the mapped column directly: this marks the
            // entity dirty (so the password-history / change-notification listeners
            // fire) without leaving a plain password for the updater to re-hash.
            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
            $this->entityManager->flush();

            // Regenerate the session id after this credential change (defense in depth).
            $session = $request->getSession();
            $session->migrate(true);

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', 'three_brs.set_password.success');
            }

            return new RedirectResponse($this->router->generate('sylius_shop_account_dashboard'));
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/SetPassword/index.html.twig',
            ['form' => $form->createView()],
        ));
    }

    protected function isPasswordless(ShopUserInterface $user): bool
    {
        $password = $user->getPassword();

        return $password === null || $password === '';
    }
}
