<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use Webmozart\Assert\Assert;

class TwoFactorTrustedDeviceContext implements Context
{
    private ?string $knownSecret = null;

    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the customer :email has 2FA enabled with a known secret
     */
    public function theCustomerHasTwoFactorEnabledWithKnownSecret(string $email): void
    {
        $this->knownSecret = TOTP::generate()->getSecret();

        $user = $this->findShopUser($email);
        $user->setTotpSecret($this->knownSecret);
        $user->setTwoFactorEnabled(true);
        $this->entityManager->flush();
    }

    /**
     * @When I submit a valid TOTP challenge code trusting this device
     */
    public function iSubmitAValidTotpChallengeCodeTrustingThisDevice(): void
    {
        Assert::notNull($this->knownSecret, 'Known secret was not stored.');
        $code = TOTP::createFromSecret($this->knownSecret)->now();

        $page = $this->session->getPage();
        $input = $page->find('css', '[data-test-two-factor-auth-code]');
        Assert::notNull($input, 'Auth code input not present on page.');
        $input->setValue($code);

        $trusted = $page->find('css', '[data-test-two-factor-trusted]');
        Assert::notNull($trusted, 'Trusted device checkbox not present on page.');
        $trusted->check();

        $button = $page->find('css', '[data-test-two-factor-submit]');
        Assert::notNull($button, 'Submit button not present on page.');
        $button->click();
    }

    /**
     * @When I sign out from the shop
     */
    public function iSignOutFromTheShop(): void
    {
        $this->session->visit('http://localhost:8080/en_US/logout');
    }

    /**
     * @When the customer :email revokes all trusted devices
     */
    public function theCustomerRevokesAllTrustedDevices(string $email): void
    {
        $user = $this->findShopUser($email);
        $user->bumpTrustedTokenVersion();
        $this->entityManager->flush();
    }

    /**
     * @Then I should be on the 2FA challenge page
     */
    public function iShouldBeOnTheTwoFactorChallengePage(): void
    {
        $page = $this->session->getPage();
        if ($page->find('css', '[data-test-two-factor-challenge]') === null) {
            throw new \RuntimeException(sprintf(
                "Expected 2FA challenge. URL=%s\nBODY:\n%s",
                $this->session->getCurrentUrl(),
                substr($page->getContent(), 0, 2000),
            ));
        }
    }

    /**
     * @Then I should be fully authenticated
     */
    public function iShouldBeFullyAuthenticated(): void
    {
        $url = $this->session->getCurrentUrl();
        $page = $this->session->getPage();
        Assert::null(
            $page->find('css', '[data-test-two-factor-challenge]'),
            sprintf('Still on 2FA challenge page (URL "%s").', $url),
        );
        if (!str_contains($url, '/en_US/') || str_contains($url, '/2fa')) {
            throw new \RuntimeException(sprintf(
                "Expected to be on shop homepage. URL=%s\nBODY:\n%s",
                $url,
                substr($page->getContent(), 0, 2000),
            ));
        }
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
}
