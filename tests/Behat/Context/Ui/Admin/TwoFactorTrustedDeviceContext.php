<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service\StableTotpCodeTrait;
use ThreeBRS\EnterpriseSecurityBundle\TwoFactor\TwoFactorAuthAdminUserInterface;
use Webmozart\Assert\Assert;

class TwoFactorTrustedDeviceContext implements Context
{
    use StableTotpCodeTrait;

    private ?string $knownSecret = null;

    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the administrator :email has 2FA enabled with a known secret
     */
    public function theAdministratorHasTwoFactorEnabledWithKnownSecret(string $email): void
    {
        $this->knownSecret = TOTP::generate()->getSecret();

        $user = $this->findAdminUser($email);
        $user->setTotpSecret($this->knownSecret);
        $user->setTwoFactorEnabled(true);
        $this->entityManager->flush();
    }

    /**
     * @When I submit a valid admin TOTP challenge code trusting this device
     */
    public function iSubmitAValidTotpChallengeCodeTrustingThisDevice(): void
    {
        Assert::notNull($this->knownSecret, 'Known secret was not stored.');
        $code = $this->generateStableTotpCode($this->knownSecret);

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
     * @When I sign out from the admin panel
     */
    public function iSignOutFromTheAdminPanel(): void
    {
        $this->session->visit('http://localhost:8080/admin/logout');
    }

    /**
     * @When the administrator :email revokes all trusted devices
     */
    public function theAdministratorRevokesAllTrustedDevices(string $email): void
    {
        $user = $this->findAdminUser($email);
        $user->bumpTrustedTokenVersion();
        $this->entityManager->flush();
    }

    /**
     * @Then I should be on the admin 2FA challenge page
     */
    public function iShouldBeOnTheTwoFactorChallengePage(): void
    {
        $page = $this->session->getPage();
        if ($page->find('css', '[data-test-two-factor-challenge]') === null) {
            throw new \RuntimeException(sprintf(
                "Expected admin 2FA challenge. URL=%s\nBODY:\n%s",
                $this->session->getCurrentUrl(),
                substr($page->getContent(), 0, 2000),
            ));
        }
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
            sprintf('Still on admin 2FA challenge page (URL "%s").', $url),
        );
        if (!str_contains($url, '/admin') || str_contains($url, '/admin/2fa')) {
            throw new \RuntimeException(sprintf(
                "Expected to be on admin dashboard. URL=%s\nBODY:\n%s",
                $url,
                substr($page->getContent(), 0, 2000),
            ));
        }
    }

    private function findAdminUser(string $email): AdminUserInterface&TwoFactorAuthAdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);
        Assert::isInstanceOf($user, TwoFactorAuthAdminUserInterface::class);

        return $user;
    }
}
