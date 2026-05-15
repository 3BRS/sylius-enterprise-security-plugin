<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\QrCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TotpSecretGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\TwoFactorVerifyType;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use Twig\Environment;

class TwoFactorSetupController implements TwoFactorSetupControllerInterface
{
    public const SESSION_PENDING_SECRET = 'three_brs_shop_two_factor_pending_secret';

    public const SESSION_PLAIN_RECOVERY_CODES = 'three_brs_shop_two_factor_plain_recovery_codes';

    public function __construct(
        protected TokenStorageInterface $tokenStorage,
        protected TotpSecretGeneratorInterface $totpGenerator,
        protected QrCodeGeneratorInterface $qrGenerator,
        protected RecoveryCodeGeneratorInterface $recoveryGenerator,
        protected EntityManagerInterface $entityManager,
        protected FormFactoryInterface $formFactory,
        protected RouterInterface $router,
        protected Environment $twig,
        protected TranslatorInterface $translator,
        protected CsrfTokenManagerInterface $csrfTokenManager,
        protected string $issuer,
        protected bool $recoveryCodesEnabled,
        protected int $recoveryCodesCount,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof ShopUserInterface || !$user instanceof TwoFactorAuthShopUserInterface) {
            return new RedirectResponse($this->router->generate('sylius_shop_login'));
        }

        if ($user->isTwoFactorEnabled()) {
            return new Response($this->twig->render(
                '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/manage.html.twig',
                [
                    'disable_csrf_token' => $this->csrfTokenManager->getToken(TwoFactorDisableController::CSRF_TOKEN_ID)->getValue(),
                    'regenerate_csrf_token' => $this->csrfTokenManager->getToken(TwoFactorRegenerateRecoveryCodesController::CSRF_TOKEN_ID)->getValue(),
                    'recovery_codes_enabled' => $this->recoveryCodesEnabled,
                ],
            ));
        }

        $session = $request->getSession();
        $secret = $session->get(self::SESSION_PENDING_SECRET);
        if (!is_string($secret) || $secret === '') {
            $secret = $this->totpGenerator->generateSecret();
            $session->set(self::SESSION_PENDING_SECRET, $secret);
        }

        $form = $this->formFactory->create(TwoFactorVerifyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = str_replace('-', '', (string) $form->get('code')->getData());
            if ($this->totpGenerator->verifyCode($secret, $code)) {
                $user->setTotpSecret($secret);
                $user->setTwoFactorEnabled(true);

                $plainCodes = [];
                if ($this->recoveryCodesEnabled) {
                    $plainCodes = $this->recoveryGenerator->generate($this->recoveryCodesCount);
                    foreach ($plainCodes as $plain) {
                        $record = new CustomerRecoveryCode();
                        $record->setShopUser($user);
                        $record->setCodeHash($this->recoveryGenerator->hash($plain));
                        $this->entityManager->persist($record);
                    }
                }

                $this->entityManager->flush();
                $session->remove(self::SESSION_PENDING_SECRET);
                $session->set(self::SESSION_PLAIN_RECOVERY_CODES, $plainCodes);

                return new RedirectResponse($this->router->generate('three_brs_shop_two_factor_recovery_codes'));
            }

            $form->get('code')->addError(new FormError(
                $this->translator->trans('three_brs.two_factor.invalid_code', [], 'validators'),
            ));
        }

        $username = (string) $user->getEmail();
        $uri = $this->totpGenerator->buildProvisioningUri($secret, $username, $this->issuer);

        return new Response($this->twig->render(
            '@ThreeBRSSyliusEnterpriseSecurityPlugin/Shop/TwoFactor/setup.html.twig',
            [
                'form' => $form->createView(),
                'qr_data_uri' => $this->qrGenerator->generateDataUri($uri),
                'secret' => $secret,
            ],
        ));
    }
}
