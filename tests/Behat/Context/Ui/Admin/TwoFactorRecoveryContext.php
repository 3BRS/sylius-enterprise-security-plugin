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
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserRecoveryCodeRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\RecoveryCodeGeneratorInterface;
use Webmozart\Assert\Assert;

class TwoFactorRecoveryContext implements Context
{
    /** @var array<int, string> */
    private array $plainRecoveryCodes = [];

    private ?string $lastUsedCode = null;

    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private EntityManagerInterface $entityManager,
        private RecoveryCodeGeneratorInterface $recoveryGenerator,
        private AdminUserRecoveryCodeRepositoryInterface $recoveryCodeRepository,
    ) {
    }

    /**
     * @Given the administrator :email has 2FA enabled with recovery codes
     */
    public function theAdministratorHasTwoFactorEnabledWithRecoveryCodes(string $email): void
    {
        $user = $this->findAdminUser($email);
        $user->setTotpSecret(TOTP::generate()->getSecret());
        $user->setTwoFactorEnabled(true);

        $this->plainRecoveryCodes = ['AAAAA-11111', 'BBBBB-22222'];
        foreach ($this->plainRecoveryCodes as $plain) {
            $record = new AdminUserRecoveryCode();
            $record->setAdminUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash($plain));
            $this->entityManager->persist($record);
        }

        $this->entityManager->flush();
    }

    /**
     * @When I visit the admin recovery code challenge page
     */
    public function iVisitTheRecoveryChallengePage(): void
    {
        $this->session->visit('http://localhost:8080/admin/2fa/recovery');
    }

    /**
     * @When I submit a valid admin recovery code
     */
    public function iSubmitAValidRecoveryCode(): void
    {
        Assert::notEmpty($this->plainRecoveryCodes, 'No recovery codes prepared.');
        $this->lastUsedCode = $this->plainRecoveryCodes[0];
        $this->submitRecoveryForm($this->lastUsedCode);
    }

    /**
     * @When I submit an invalid admin recovery code
     */
    public function iSubmitAnInvalidRecoveryCode(): void
    {
        $this->submitRecoveryForm('ZZZZZ-99999');
    }

    /**
     * @Then I should see an admin recovery code error
     */
    public function iShouldSeeARecoveryCodeError(): void
    {
        Assert::notNull(
            $this->session->getPage()->find('css', '[data-test-two-factor-recovery-error]'),
            sprintf('No recovery error on page (URL "%s").', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then I should be fully authenticated as administrator
     */
    public function iShouldBeFullyAuthenticatedAsAdmin(): void
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
        if (!str_contains($url, '/admin') || str_contains($url, '/admin/2fa')) {
            throw new \RuntimeException(sprintf(
                "Expected to be on admin dashboard. URL=%s\nBODY:\n%s",
                $url,
                substr($page->getContent(), 0, 2000),
            ));
        }
    }

    /**
     * @Then the used admin recovery code should be marked consumed
     */
    public function theUsedRecoveryCodeShouldBeMarkedConsumed(): void
    {
        Assert::notNull($this->lastUsedCode, 'No recovery code was used in this scenario.');
        $this->entityManager->clear();
        $hash = $this->recoveryGenerator->hash($this->lastUsedCode);
        $records = $this->entityManager
            ->getRepository(AdminUserRecoveryCode::class)
            ->findBy(['codeHash' => $hash]);
        Assert::count($records, 1, 'Expected exactly one recovery code record.');
        Assert::notNull($records[0]->getUsedAt(), 'Recovery code was not marked consumed.');
    }

    private function findAdminUser(string $email): AdminUserInterface&TwoFactorAuthAdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);
        Assert::isInstanceOf($user, TwoFactorAuthAdminUserInterface::class);

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
