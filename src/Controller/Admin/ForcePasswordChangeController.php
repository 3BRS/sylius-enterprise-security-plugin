<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\UserBundle\Form\Model\ChangePassword;
use Sylius\Bundle\UserBundle\Form\Type\UserChangePasswordType;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class ForcePasswordChangeController implements ForcePasswordChangeControllerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private Environment $twig,
        private FormFactoryInterface $formFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof AdminUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        $model = new ChangePassword();
        $form = $this->formFactory->create(UserChangePasswordType::class, $model, ['validation_groups' => ['sylius']]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $model->getNewPassword();
            if ($newPassword !== null) {
                $user->setPlainPassword($newPassword);
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $this->entityManager->flush();
            }

            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();

            return new RedirectResponse($this->router->generate('sylius_admin_login'));
        }

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Admin/ForcePasswordChange/index.html.twig',
            ['form' => $form->createView()],
        ));
    }
}
