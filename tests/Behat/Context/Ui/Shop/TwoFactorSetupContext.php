<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\StableTotpCodeTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerRecoveryCodeRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class TwoFactorSetupContext implements Context
{
    use StableTotpCodeTrait;

    protected ?string $secret = null;

    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
        protected CustomerRecoveryCodeRepositoryInterface $recoveryCodeRepository,
        protected RecoveryCodeGeneratorInterface $recoveryGenerator,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * @Given the customer :email already has 2FA enabled
     */
    public function theCustomerAlreadyHasTwoFactorEnabled(string $email): void
    {
        $user = $this->findShopUser($email);
        $this->secret = TOTP::generate()->getSecret();
        $user->setTotpSecret($this->secret);
        $user->setTwoFactorEnabled(true);
        $this->entityManager->flush();
    }

    /**
     * @Given the customer :email already has 2FA enabled with recovery codes
     */
    public function theCustomerAlreadyHasTwoFactorEnabledWithRecoveryCodes(string $email): void
    {
        $user = $this->findShopUser($email);
        $this->secret = TOTP::generate()->getSecret();
        $user->setTotpSecret($this->secret);
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
        $code = $this->generateStableTotpCode($secret);
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
     * @When I disable 2FA confirming with the current authenticator code
     */
    public function iDisableTwoFactorConfirmingWithTheCurrentAuthenticatorCode(): void
    {
        $this->submitConfirmedForm('disable', $this->currentCode());
    }

    /**
     * @When I disable 2FA confirming with the current authenticator code written with a dash
     */
    public function iDisableTwoFactorConfirmingWithTheCurrentAuthenticatorCodeWrittenWithADash(): void
    {
        $code = $this->currentCode();
        $this->submitConfirmedForm('disable', substr($code, 0, 3) . '-' . substr($code, 3));
    }

    /**
     * @When I disable 2FA confirming with the recovery code :code
     * @When I try to disable 2FA with the code :code
     */
    public function iDisableTwoFactorWithTheCode(string $code): void
    {
        $this->submitConfirmedForm('disable', $code);
    }

    /**
     * @When I try to disable 2FA with the code :code :count times
     */
    public function iTryToDisableTwoFactorWithTheCodeRepeatedly(string $code, int $count): void
    {
        for ($attempt = 0; $attempt < $count; ++$attempt) {
            $this->submitConfirmedForm('disable', $code);
        }
    }

    /**
     * @When I regenerate recovery codes confirming with the current authenticator code
     */
    public function iRegenerateRecoveryCodesConfirmingWithTheCurrentAuthenticatorCode(): void
    {
        $this->submitConfirmedForm('regenerate', $this->currentCode());
    }

    /**
     * @When I try to regenerate recovery codes with the code :code
     */
    public function iTryToRegenerateRecoveryCodesWithTheCode(string $code): void
    {
        $this->submitConfirmedForm('regenerate', $code);
    }

    /**
     * @Then I should be told the two-factor code is invalid
     */
    public function iShouldBeToldTheTwoFactorCodeIsInvalid(): void
    {
        $this->assertFlash('three_brs.two_factor.confirmation_code_invalid');
    }

    /**
     * @Then I should be told to try again later
     */
    public function iShouldBeToldToTryAgainLater(): void
    {
        $this->assertFlash('three_brs.rate_limit.too_many_requests');
    }

    /**
     * @Then none of the previous recovery codes should work for :email
     */
    public function noneOfThePreviousRecoveryCodesShouldWorkFor(string $email): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);

        foreach (['AAAAA-11111', 'BBBBB-22222'] as $previous) {
            $record = $this->recoveryCodeRepository->findUnusedByShopUserAndHash(
                $user,
                $this->recoveryGenerator->hash($previous),
            );
            Assert::null($record, sprintf('Previous recovery code "%s" still matches.', $previous));
        }
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
        Assert::same(
            $this->entityManager->getRepository(CustomerRecoveryCode::class)->findBy(['shopUser' => $user]),
            [],
        );
    }

    protected function findShopUser(string $email): ShopUserInterface&TwoFactorAuthShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);
        Assert::isInstanceOf($user, TwoFactorAuthShopUserInterface::class);

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

    protected function assertFlash(string $message): void
    {
        Assert::contains(
            $this->session->getPage()->getText(),
            $this->translator->trans($message, [], 'flashes'),
        );
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
