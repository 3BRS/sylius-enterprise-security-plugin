<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\FakeOAuthStateInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSocialAccountLinkRepositoryInterface;
use Webmozart\Assert\Assert;

class SocialLoginContext implements Context
{
    /**
     * @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository
     */
    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private AdminUserSocialAccountLinkRepositoryInterface $linkRepository,
        private EntityManagerInterface $entityManager,
        private FakeOAuthStateInterface $fakeOAuthState,
        private SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * @Given the :provider OAuth provider will return admin user :providerUserId with email :email
     */
    public function theOAuthProviderWillReturnAdmin(string $provider, string $providerUserId, string $email): void
    {
        $this->fakeOAuthState->seedUserInfo($provider, $providerUserId, $email, 'Admin', 'Social');
    }

    /**
     * @Given the admin :email is already linked to :provider with id :providerUserId
     */
    public function theAdminIsAlreadyLinkedTo(string $email, string $provider, string $providerUserId): void
    {
        $admin = $this->findAdmin($email);

        $link = new AdminUserSocialAccountLink();
        $link->setAdminUser($admin);
        $link->setProvider($provider);
        $link->setProviderUserId($providerUserId);
        $link->setEmail($email);

        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    /**
     * @When I click the admin :provider social login button
     */
    public function iClickTheAdminSocialLoginButton(string $provider): void
    {
        $this->session->visit('/admin/login');
        $button = $this->session->getPage()->find('css', sprintf('[data-test-three-brs-admin-social-login="%s"]', $provider));
        Assert::notNull($button, sprintf('Admin social login button for "%s" not found on page.', $provider));
        $button->click();
    }

    /**
     * @When I confirm the admin social link with password :password
     */
    public function iConfirmAdminSocialLinkWithPassword(string $password): void
    {
        $page = $this->session->getPage();
        $input = $page->find('css', '[data-test-three-brs-social-confirm-password]');
        Assert::notNull($input, 'Admin password confirm input not found on confirm-link page.');
        $input->setValue($this->retrieveSecurePassword($password));

        $button = $page->find('css', '[data-test-three-brs-social-confirm-submit]');
        Assert::notNull($button, 'Admin confirm submit button not found.');
        $button->click();
    }

    /**
     * @Given the administrator :email has no usable password
     */
    public function theAdministratorHasNoUsablePassword(string $email): void
    {
        $admin = $this->findAdmin($email);
        $admin->setPassword(null);
        $this->entityManager->flush();
    }

    /**
     * @When I click the admin :provider link button on the social accounts page
     */
    public function iClickTheAdminLinkButtonOnTheSocialAccountsPage(string $provider): void
    {
        $this->session->visit('/admin/social-accounts');
        $button = $this->session->getPage()->find('css', sprintf('[data-test-three-brs-social-link="%s"]', $provider));
        Assert::notNull($button, sprintf('Admin social link button for "%s" not found on social accounts page.', $provider));
        $button->click();
    }

    /**
     * @When I unlink my admin :provider social account
     */
    public function iUnlinkMyAdminSocialAccount(string $provider): void
    {
        $this->session->visit('/admin/social-accounts');
        $button = $this->session->getPage()->find('css', sprintf('[data-test-three-brs-social-unlink="%s"]', $provider));
        Assert::notNull($button, sprintf('Admin unlink button for "%s" not found on social accounts page.', $provider));
        $button->click();
    }

    /**
     * @Then the administrator :email should not be linked to :provider
     */
    public function theAdministratorShouldNotBeLinkedTo(string $email, string $provider): void
    {
        $this->entityManager->clear();
        $admin = $this->findAdmin($email);
        $link = $this->linkRepository->findOneByAdminUserAndProvider($admin, $provider);
        Assert::null($link, sprintf('Expected no "%s" link for admin "%s", but found one.', $provider, $email));
    }

    /**
     * @Then the administrator :email should still be linked to :provider
     */
    public function theAdministratorShouldStillBeLinkedTo(string $email, string $provider): void
    {
        $this->entityManager->clear();
        $admin = $this->findAdmin($email);
        $link = $this->linkRepository->findOneByAdminUserAndProvider($admin, $provider);
        Assert::notNull($link, sprintf('Expected "%s" link for admin "%s" to still exist.', $provider, $email));
    }

    /**
     * @Then I should be on the admin social link password-confirm page
     */
    public function iShouldBeOnTheAdminConfirmLinkPage(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::contains($url, '/admin/oauth/confirm-link', sprintf('Expected admin confirm-link URL, got "%s".', $url));
    }

    /**
     * @Then I should be logged in as admin :email
     */
    public function iShouldBeLoggedInAsAdmin(string $email): void
    {
        $url = $this->session->getCurrentUrl();

        if (str_contains($url, '/admin/login') || str_contains($url, '/admin/oauth/')) {
            $html = $this->session->getPage()->getHtml();
            $body = preg_replace('/\s+/', ' ', strip_tags($html));
            throw new \RuntimeException(sprintf(
                "URL='%s' BODY=%s",
                $url,
                substr((string) $body, 0, 3000),
            ));
        }

        $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($admin, sprintf('Admin "%s" not found after social login.', $email));
    }

    /**
     * @Then an admin social link should exist for :email with :provider and provider id :providerUserId
     */
    public function anAdminSocialLinkShouldExistFor(string $email, string $provider, string $providerUserId): void
    {
        $this->entityManager->clear();
        $link = $this->linkRepository->findByProviderAndProviderUserId($provider, $providerUserId);
        Assert::notNull($link, sprintf('No %s admin link found for provider_user_id "%s".', $provider, $providerUserId));
        Assert::same($link->getEmail(), $email);
    }

    /**
     * @Then the admin :email should exist
     */
    public function theAdminShouldExist(string $email): void
    {
        $this->entityManager->clear();
        $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($admin, sprintf('Admin "%s" was not created.', $email));
    }

    /**
     * @Then I should see an admin social-login error
     */
    public function iShouldSeeAnAdminSocialLoginError(): void
    {
        $page = $this->session->getPage();
        Assert::notNull(
            $page->find('css', '[data-test-three-brs-social-confirm-error]'),
            'Expected admin password-confirm error to be visible.',
        );
    }

    private function findAdmin(string $email): AdminUserInterface
    {
        $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($admin, sprintf('Admin "%s" not found.', $email));
        Assert::isInstanceOf($admin, AdminUserInterface::class);

        return $admin;
    }

    private function retrieveSecurePassword(string $password): string
    {
        $scenarioSetupPassword = $this->sharedStorage->has('scenario_setup_password')
            ? $this->sharedStorage->get('scenario_setup_password')
            : null;

        if ($scenarioSetupPassword === $password && $this->sharedStorage->has('password')) {
            return (string) $this->sharedStorage->get('password');
        }

        return $password;
    }
}
