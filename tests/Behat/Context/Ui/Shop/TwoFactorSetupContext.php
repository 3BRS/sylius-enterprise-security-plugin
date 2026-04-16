<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeGeneratorInterface;
use Webmozart\Assert\Assert;

class TwoFactorSetupContext implements Context
{
    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private EntityManagerInterface $entityManager,
        private CustomerRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        private RecoveryCodeGeneratorInterface $recoveryGenerator,
    ) {
    }

    /**
     * @Given the customer :email already has 2FA enabled
     */
    public function theCustomerAlreadyHasTwoFactorEnabled(string $email): void
    {
        $user = $this->findShopUser($email);
        $user->setTotpSecret(TOTP::generate()->getSecret());
        $user->setTwoFactorEnabled(true);
        $this->entityManager->flush();
    }

    /**
     * @Given the customer :email already has 2FA enabled with recovery codes
     */
    public function theCustomerAlreadyHasTwoFactorEnabledWithRecoveryCodes(string $email): void
    {
        $user = $this->findShopUser($email);
        $user->setTotpSecret(TOTP::generate()->getSecret());
        $user->setTwoFactorEnabled(true);

        foreach (['AAAAA-11111', 'BBBBB-22222'] as $plain) {
            $record = new CustomerRecoveryCode();
            $record->setShopUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    /**
     * @When I visit the 2FA setup page
     */
    public function iVisitTheTwoFactorSetupPage(): void
    {
        $this->session->visit('/en_US/account/two-factor/setup');
    }

    /**
     * @When I submit a valid TOTP code
     */
    public function iSubmitAValidTotpCode(): void
    {
        $secret = $this->readSecretFromPage();
        $code = TOTP::createFromSecret($secret)->now();
        $this->submitSetupForm($code);
    }

    /**
     * @When I submit an invalid TOTP code
     */
    public function iSubmitAnInvalidTotpCode(): void
    {
        $this->submitSetupForm('000000');
    }

    /**
     * @When I press the 2FA disable button
     */
    public function iPressTheTwoFactorDisableButton(): void
    {
        $page = $this->session->getPage();
        $button = $page->find('css', '[data-test-two-factor-disable]');
        Assert::notNull($button, 'Disable button not present on page.');
        $button->click();
    }

    /**
     * @Then I should see the 2FA disable button
     */
    public function iShouldSeeTheTwoFactorDisableButton(): void
    {
        Assert::notNull($this->session->getPage()->find('css', '[data-test-two-factor-disable]'));
    }

    /**
     * @Then I should see the 2FA QR code
     */
    public function iShouldSeeTheTwoFactorQrCode(): void
    {
        Assert::notNull($this->session->getPage()->find('css', '[data-test-two-factor-qr]'));
    }

    /**
     * @Then I should see the TOTP secret
     */
    public function iShouldSeeTheTotpSecret(): void
    {
        $secret = $this->readSecretFromPage();
        Assert::notEmpty($secret);
    }

    /**
     * @Then I should be on the 2FA recovery codes page
     */
    public function iShouldBeOnTheRecoveryCodesPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), '/account/two-factor/recovery-codes'),
            sprintf('Expected recovery codes URL, got "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then I should see :count recovery codes
     */
    public function iShouldSeeRecoveryCodes(int $count): void
    {
        $codes = $this->session->getPage()->findAll('css', '[data-test-recovery-code]');
        Assert::count($codes, $count);
    }

    /**
     * @Then 2FA should be enabled for :email
     */
    public function twoFactorShouldBeEnabledFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);
        Assert::true($user->isTwoFactorEnabled());
        Assert::notNull($user->getTotpSecret());
    }

    /**
     * @Then 2FA should not be enabled for :email
     */
    public function twoFactorShouldNotBeEnabledFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);
        Assert::false($user->isTwoFactorEnabled());
    }

    /**
     * @Then I should remain on the 2FA setup page
     */
    public function iShouldRemainOnTheSetupPage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), '/account/two-factor/setup'),
            sprintf('Expected to remain on setup page, got "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then the customer should have no recovery codes
     */
    public function theCustomerShouldHaveNoRecoveryCodes(): void
    {
        $user = $this->findShopUser('john@example.com');
        Assert::same($this->recoveryCodeRepository->findAllByShopUser($user), []);
    }

    private function findShopUser(string $email): ShopUserInterface&TwoFactorAuthShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);
        Assert::isInstanceOf($user, TwoFactorAuthShopUserInterface::class);

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
