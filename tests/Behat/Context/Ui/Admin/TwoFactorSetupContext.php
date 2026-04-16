<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeGeneratorInterface;
use Webmozart\Assert\Assert;

class TwoFactorSetupContext implements Context
{
    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private EntityManagerInterface $entityManager,
        private AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        private RecoveryCodeGeneratorInterface $recoveryGenerator,
    ) {
    }

    /**
     * @Given the administrator :email already has 2FA enabled
     */
    public function theAdministratorAlreadyHasTwoFactorEnabled(string $email): void
    {
        $user = $this->findAdminUser($email);
        $user->setTotpSecret(TOTP::generate()->getSecret());
        $user->setTwoFactorEnabled(true);
        $this->entityManager->flush();
    }

    /**
     * @Given the administrator :email already has 2FA enabled with recovery codes
     */
    public function theAdministratorAlreadyHasTwoFactorEnabledWithRecoveryCodes(string $email): void
    {
        $user = $this->findAdminUser($email);
        $user->setTotpSecret(TOTP::generate()->getSecret());
        $user->setTwoFactorEnabled(true);

        foreach (['AAAAA-11111', 'BBBBB-22222'] as $plain) {
            $record = new AdminUserRecoveryCode();
            $record->setAdminUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    /**
     * @When I visit the admin 2FA setup page
     */
    public function iVisitTheTwoFactorSetupPage(): void
    {
        $this->session->visit('/admin/two-factor/setup');
    }

    /**
     * @When I submit a valid admin TOTP code
     */
    public function iSubmitAValidTotpCode(): void
    {
        $secret = $this->readSecretFromPage();
        $code = TOTP::createFromSecret($secret)->now();
        $this->submitSetupForm($code);
    }

    /**
     * @When I submit an invalid admin TOTP code
     */
    public function iSubmitAnInvalidTotpCode(): void
    {
        $this->submitSetupForm('000000');
    }

    /**
     * @When I press the admin 2FA disable button
     */
    public function iPressTheTwoFactorDisableButton(): void
    {
        $button = $this->session->getPage()->find('css', '[data-test-two-factor-disable]');
        Assert::notNull($button, 'Disable button not present on page.');
        $button->click();
    }

    /**
     * @Then I should see the admin 2FA QR code
     */
    public function iShouldSeeTheTwoFactorQrCode(): void
    {
        Assert::notNull($this->session->getPage()->find('css', '[data-test-two-factor-qr]'));
    }

    /**
     * @Then I should see the admin TOTP secret
     */
    public function iShouldSeeTheTotpSecret(): void
    {
        Assert::notEmpty($this->readSecretFromPage());
    }

    /**
     * @Then I should see the admin 2FA disable button
     */
    public function iShouldSeeTheTwoFactorDisableButton(): void
    {
        Assert::notNull($this->session->getPage()->find('css', '[data-test-two-factor-disable]'));
    }

    /**
     * @Then I should be on the admin 2FA recovery codes page
     */
    public function iShouldBeOnTheRecoveryCodesPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), '/admin/two-factor/recovery-codes'),
            sprintf('Expected recovery codes URL, got "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then I should see :count admin recovery codes
     */
    public function iShouldSeeRecoveryCodes(int $count): void
    {
        $codes = $this->session->getPage()->findAll('css', '[data-test-recovery-code]');
        Assert::count($codes, $count);
    }

    /**
     * @Then admin 2FA should be enabled for :email
     */
    public function twoFactorShouldBeEnabledFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);
        Assert::true($user->isTwoFactorEnabled());
        Assert::notNull($user->getTotpSecret());
    }

    /**
     * @Then admin 2FA should not be enabled for :email
     */
    public function twoFactorShouldNotBeEnabledFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findAdminUser($email);
        Assert::false($user->isTwoFactorEnabled());
    }

    /**
     * @Then I should remain on the admin 2FA setup page
     */
    public function iShouldRemainOnTheSetupPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), '/admin/two-factor/setup'),
            sprintf('Expected to remain on setup page, got "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then the administrator should have no recovery codes
     */
    public function theAdministratorShouldHaveNoRecoveryCodes(): void
    {
        $user = $this->findAdminUser('admin@example.com');
        Assert::same($this->recoveryCodeRepository->findAllByAdminUser($user), []);
    }

    private function findAdminUser(string $email): AdminUserInterface&TwoFactorAuthAdminUserInterface
    {
        $user = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);
        Assert::isInstanceOf($user, TwoFactorAuthAdminUserInterface::class);

        return $user;
    }

    private function readSecretFromPage(): string
    {
        $element = $this->session->getPage()->find('css', '[data-test-two-factor-secret]');
        Assert::notNull($element, 'Secret element not present on page.');

        return trim($element->getText());
    }

    private function submitSetupForm(string $code): void
    {
        $page = $this->session->getPage();
        $page->fillField('three_brs_two_factor_verify[code]', $code);
        $page->pressButton('three_brs.ui.two_factor.verify_and_enable');
    }
}
