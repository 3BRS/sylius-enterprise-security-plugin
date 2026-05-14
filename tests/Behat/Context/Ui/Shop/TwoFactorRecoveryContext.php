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

class TwoFactorRecoveryContext implements Context
{
    /** @var array<int, string> */
    private array $plainRecoveryCodes = [];

    private ?string $lastUsedCode = null;

    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private EntityManagerInterface $entityManager,
        private RecoveryCodeGeneratorInterface $recoveryGenerator,
        private CustomerRecoveryCodeRepositoryInterface $recoveryCodeRepository,
    ) {
    }

    /**
     * @Given the customer :email has 2FA enabled with recovery codes
     */
    public function theCustomerHasTwoFactorEnabledWithRecoveryCodes(string $email): void
    {
        $user = $this->findShopUser($email);
        $user->setTotpSecret(TOTP::generate()->getSecret());
        $user->setTwoFactorEnabled(true);

        $this->plainRecoveryCodes = ['AAAAA-11111', 'BBBBB-22222'];
        foreach ($this->plainRecoveryCodes as $plain) {
            $record = new CustomerRecoveryCode();
            $record->setShopUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    /**
     * @When I visit the recovery code challenge page
     */
    public function iVisitTheRecoveryChallengePage(): void
    {
        $this->session->visit('http://localhost:8080/2fa/recovery');
    }

    /**
     * @When I submit a valid recovery code
     */
    public function iSubmitAValidRecoveryCode(): void
    {
        Assert::notEmpty($this->plainRecoveryCodes, 'No recovery codes prepared.');
        $this->lastUsedCode = $this->plainRecoveryCodes[0];
        $this->submitRecoveryForm($this->lastUsedCode);
    }

    /**
     * @When I submit the same recovery code again
     */
    public function iSubmitTheSameRecoveryCodeAgain(): void
    {
        Assert::notNull($this->lastUsedCode, 'No previously used recovery code.');
        $this->submitRecoveryForm($this->lastUsedCode);
    }

    /**
     * @When I submit an invalid recovery code
     */
    public function iSubmitAnInvalidRecoveryCode(): void
    {
        $this->submitRecoveryForm('ZZZZZ-99999');
    }

    /**
     * @Then I should see a recovery code error
     */
    public function iShouldSeeARecoveryCodeError(): void
    {
        Assert::notNull(
            $this->session->getPage()->find('css', '[data-test-two-factor-recovery-error]'),
            sprintf('No recovery error on page (URL "%s").', $this->session->getCurrentUrl()),
        );
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
        Assert::null(
            $page->find('css', '[data-test-two-factor-recovery-challenge]'),
            sprintf('Still on recovery challenge page (URL "%s").', $url),
        );
        if (!str_contains($url, '/en_US/') || str_contains($url, '/2fa')) {
            throw new \RuntimeException(sprintf(
                "Expected to be on shop homepage. URL=%s\nBODY:\n%s",
                $url,
                substr($page->getContent(), 0, 2000),
            ));
        }
    }

    /**
     * @Then the used recovery code should be marked consumed
     */
    public function theUsedRecoveryCodeShouldBeMarkedConsumed(): void
    {
        Assert::notNull($this->lastUsedCode, 'No recovery code was used in this scenario.');
        $this->entityManager->clear();
        $hash = $this->recoveryGenerator->hash($this->lastUsedCode);
        $records = $this->entityManager
            ->getRepository(CustomerRecoveryCode::class)
            ->findBy(['codeHash' => $hash]);
        Assert::count($records, 1, 'Expected exactly one recovery code record.');
        Assert::notNull($records[0]->getUsedAt(), 'Recovery code was not marked consumed.');
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

    private function submitRecoveryForm(string $code): void
    {
        $page = $this->session->getPage();
        $input = $page->find('css', '[data-test-two-factor-recovery-code]');
        Assert::notNull($input, sprintf("Recovery code input not present (URL %s).", $this->session->getCurrentUrl()));
        $input->setValue($code);

        $button = $page->find('css', '[data-test-two-factor-recovery-submit]');
        Assert::notNull($button, 'Recovery submit button not present.');
        $button->click();
    }
}
