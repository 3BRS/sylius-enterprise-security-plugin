<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\StableTotpCodeTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use Webmozart\Assert\Assert;

class TwoFactorSetupContext implements Context
{
    use StableTotpCodeTrait;

    protected ?string $secret = null;

    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $adminUserRepository,
        protected EntityManagerInterface $entityManager,
        protected AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        protected RecoveryCodeGeneratorInterface $recoveryGenerator,
        protected TranslatorInterface $translator,
    ) {
    }

    #[Given('the administrator :email already has 2FA enabled')]
    public function theAdministratorAlreadyHasTwoFactorEnabled(string $email): void
    {
        $user = $this->findAdminUser($email);
        $this->secret = TOTP::generate()->getSecret();
        $user->setTotpSecret($this->secret);
        $user->setTwoFactorEnabled(true);
        $this->entityManager->flush();
    }

    #[Given('the administrator :email already has 2FA enabled with recovery codes')]
    public function theAdministratorAlreadyHasTwoFactorEnabledWithRecoveryCodes(string $email): void
    {
        $user = $this->findAdminUser($email);
        $this->secret = TOTP::generate()->getSecret();
        $user->setTotpSecret($this->secret);
        $user->setTwoFactorEnabled(true);

        foreach (['AAAAA-11111', 'BBBBB-22222'] as $plain) {
            $record = new AdminUserRecoveryCode();
            $record->setAdminUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    #[When('I visit the admin 2FA setup page')]
    public function iVisitTheTwoFactorSetupPage(): void
    {
        $this->session->visit('/admin/two-factor/setup');
    }

    #[When('I submit a valid admin TOTP code')]
    public function iSubmitAValidTotpCode(): void
    {
        $secret = $this->readSecretFromPage();
        $code = $this->generateStableTotpCode($secret);
        $this->submitSetupForm($code);
    }

    #[When('I submit an invalid admin TOTP code')]
    public function iSubmitAnInvalidTotpCode(): void
    {
        $this->submitSetupForm('000000');
    }

    #[When('I disable admin 2FA confirming with the current authenticator code')]
    public function iDisableAdminTwoFactorConfirmingWithTheCurrentAuthenticatorCode(): void
    {
        $this->submitConfirmedForm('disable', $this->currentCode());
    }

    #[When('I disable admin 2FA confirming with the current authenticator code written with a dash')]
    public function iDisableAdminTwoFactorConfirmingWithTheCurrentAuthenticatorCodeWrittenWithADash(): void
    {
        $code = $this->currentCode();
        $this->submitConfirmedForm('disable', substr($code, 0, 3) . '-' . substr($code, 3));
    }

    #[When('I disable admin 2FA confirming with the recovery code :code')]
    #[When('I try to disable admin 2FA with the code :code')]
    public function iDisableAdminTwoFactorWithTheCode(string $code): void
    {
        $this->submitConfirmedForm('disable', $code);
    }

    #[When('I try to disable admin 2FA with the code :code :count times')]
    public function iTryToDisableAdminTwoFactorWithTheCodeRepeatedly(string $code, int $count): void
    {
        for ($attempt = 0; $attempt < $count; ++$attempt) {
            $this->submitConfirmedForm('disable', $code);
        }
    }

    #[When('I regenerate admin recovery codes confirming with the current authenticator code')]
    public function iRegenerateAdminRecoveryCodesConfirmingWithTheCurrentAuthenticatorCode(): void
    {
        $this->submitConfirmedForm('regenerate', $this->currentCode());
    }

    #[When('I try to regenerate admin recovery codes with the code :code')]
    public function iTryToRegenerateAdminRecoveryCodesWithTheCode(string $code): void
    {
        $this->submitConfirmedForm('regenerate', $code);
    }

    #[Then('I should be told the admin two-factor code is invalid')]
    public function iShouldBeToldTheAdminTwoFactorCodeIsInvalid(): void
    {
        $this->assertFlashShownOnce('three_brs.two_factor.confirmation_code_invalid');
    }

    #[Then('I should be told to try the admin two-factor code again later')]
    public function iShouldBeToldToTryTheAdminTwoFactorCodeAgainLater(): void
    {
        $this->assertFlashShownOnce('three_brs.rate_limit.too_many_requests');
    }

    #[Then('none of the previous admin recovery codes should work for :email')]
    public function noneOfThePreviousRecoveryCodesShouldWorkFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);

        foreach (['AAAAA-11111', 'BBBBB-22222'] as $previous) {
            $record = $this->recoveryCodeRepository->findUnusedByAdminUserAndHash(
                $user,
                $this->recoveryGenerator->hash($previous),
            );
            Assert::null($record, sprintf('Previous recovery code "%s" still matches.', $previous));
        }
    }

    #[Then('I should see the admin 2FA QR code')]
    public function iShouldSeeTheTwoFactorQrCode(): void
    {
        Assert::notNull($this->session->getPage()->find('css', '[data-test-two-factor-qr]'));
    }

    #[Then('I should see the admin TOTP secret')]
    public function iShouldSeeTheTotpSecret(): void
    {
        Assert::notEmpty($this->readSecretFromPage());
    }

    #[Then('I should see the admin 2FA disable button')]
    public function iShouldSeeTheTwoFactorDisableButton(): void
    {
        Assert::notNull($this->session->getPage()->find('css', '[data-test-two-factor-disable]'));
    }

    #[Then('I should be on the admin 2FA recovery codes page')]
    public function iShouldBeOnTheRecoveryCodesPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), '/admin/two-factor/recovery-codes'),
            sprintf('Expected recovery codes URL, got "%s".', $this->session->getCurrentUrl()),
        );
    }

    #[Then('I should see :count admin recovery codes')]
    public function iShouldSeeRecoveryCodes(int $count): void
    {
        $codes = $this->session->getPage()->findAll('css', '[data-test-recovery-code]');
        Assert::count($codes, $count);
    }

    #[Then('admin 2FA should be enabled for :email')]
    public function twoFactorShouldBeEnabledFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);
        Assert::true($user->isTwoFactorEnabled());
        Assert::notNull($user->getTotpSecret());
    }

    #[Then('admin 2FA should not be enabled for :email')]
    public function twoFactorShouldNotBeEnabledFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);
        Assert::false($user->isTwoFactorEnabled());
    }

    #[Then('I should remain on the admin 2FA setup page')]
    public function iShouldRemainOnTheSetupPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), '/admin/two-factor/setup'),
            sprintf('Expected to remain on setup page, got "%s".', $this->session->getCurrentUrl()),
        );
    }

    #[Then('the administrator should have no recovery codes')]
    public function theAdministratorShouldHaveNoRecoveryCodes(): void
    {
        $user = $this->findAdminUser('admin@example.com');
        Assert::same(
            $this->entityManager->getRepository(AdminUserRecoveryCode::class)->findBy(['adminUser' => $user]),
            [],
        );
    }

    protected function findAdminUser(string $email): AdminUserInterface&TwoFactorAuthAdminUserInterface
    {
        $user = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);
        Assert::isInstanceOf($user, TwoFactorAuthAdminUserInterface::class);

        return $user;
    }

    protected function readSecretFromPage(): string
    {
        $element = $this->session->getPage()->find('css', '[data-test-two-factor-secret]');
        Assert::notNull($element, 'Secret element not present on page.');

        return trim($element->getText());
    }

    protected function currentCode(): string
    {
        Assert::notNull($this->secret, 'No TOTP secret was set up in this scenario.');

        return $this->generateStableTotpCode($this->secret);
    }

    protected function submitConfirmedForm(string $action, string $code): void
    {
        $page = $this->session->getPage();
        $field = $page->find('css', sprintf('[data-test-two-factor-%s-code]', $action));
        Assert::notNull($field, sprintf('The %s code field is not on the page (URL "%s").', $action, $this->session->getCurrentUrl()));
        $field->setValue($code);

        $button = $page->find('css', $action === 'disable' ? '[data-test-two-factor-disable]' : '[data-test-two-factor-regenerate-recovery-codes]');
        Assert::notNull($button, sprintf('The %s button is not on the page.', $action));
        $button->click();
    }

    protected function assertFlashShownOnce(string $message): void
    {
        $text = $this->translator->trans($message, [], 'flashes');
        $flashes = array_filter(
            $this->session->getPage()->findAll('css', '[data-test-sylius-flash-message]'),
            static fn (NodeElement $flash): bool => str_contains($flash->getText(), $text),
        );

        Assert::count($flashes, 1, sprintf('Expected the flash "%s" %%d time(s) on "%s", found it %%d times.', $text, $this->session->getCurrentUrl()));
    }

    protected function submitSetupForm(string $code): void
    {
        $page = $this->session->getPage();
        $page->fillField('three_brs_two_factor_verify[code]', $code);

        $button = $page->find('css', '[data-test-two-factor-verify]');
        Assert::notNull($button, 'Verify button not present on page.');
        $button->click();
    }
}
