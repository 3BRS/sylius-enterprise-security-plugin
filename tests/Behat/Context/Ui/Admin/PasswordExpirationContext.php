<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use Webmozart\Assert\Assert;

class PasswordExpirationContext implements Context
{
    public function __construct(
        private Session $session,
        private UserRepositoryInterface $adminUserRepository,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * @Given the administrator :email is forced to change their password
     */
    public function theAdministratorIsForcedToChangeTheirPassword(string $email): void
    {
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($adminUser, PasswordExpirationAdminUserInterface::class);

        $adminUser->setForcePasswordChange(true);
        $this->entityManager->flush();
    }

    /**
     * @When I try to open the admin dashboard
     */
    public function iTryToOpenTheAdminDashboard(): void
    {
        $this->session->visit('/admin/');
    }

    /**
     * @Then I should be on the admin force password change page
     */
    public function iShouldBeOnTheAdminForcePasswordChangePage(): void
    {
        Assert::true(
            str_contains($this->session->getCurrentUrl(), 'force-password-change'),
            sprintf('Expected to be on the force password change page, but current URL is "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @Then I should not be on the admin force password change page
     */
    public function iShouldNotBeOnTheAdminForcePasswordChangePage(): void
    {
        Assert::false(
            str_contains($this->session->getCurrentUrl(), 'force-password-change'),
            sprintf('Expected NOT to be on force password change page, but current URL is "%s".', $this->session->getCurrentUrl()),
        );
    }

    /**
     * @When I want to edit administrator :email
     */
    public function iWantToEditAdministrator(string $email): void
    {
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));

        $this->session->visit($this->router->generate('sylius_admin_admin_user_update', ['id' => $adminUser->getId()]));
    }

    /**
     * @When I check the force password change checkbox
     */
    public function iCheckTheForcePasswordChangeCheckbox(): void
    {
        $this->session->getPage()->find('css', '#sylius_admin_admin_user_forcePasswordChange')?->check();
    }

    /**
     * @When I save my changes
     */
    public function iSaveMyChanges(): void
    {
        $this->session->getPage()->find('css', '[data-test-update-changes-button]')?->click();
    }

    /**
     * @Then administrator :email should be forced to change their password on next login
     */
    public function administratorShouldBeForcedToChangePasswordOnNextLogin(string $email): void
    {
        $this->entityManager->clear();
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($adminUser, PasswordExpirationAdminUserInterface::class);
        Assert::true(
            $adminUser->isForcePasswordChange(),
            sprintf('Expected administrator "%s" to have forcePasswordChange = true.', $email),
        );
    }

    /**
     * @When I submit the force password change form with current password :currentPassword and new password :newPassword
     */
    public function iSubmitTheForcePasswordChangeForm(string $currentPassword, string $newPassword): void
    {
        $actualCurrentPassword = $currentPassword === $this->sharedStorage->get('scenario_setup_password')
            ? $this->sharedStorage->get('password')
            : $currentPassword;

        $page = $this->session->getPage();
        $page->find('css', '#sylius_user_change_password_currentPassword')?->setValue($actualCurrentPassword);
        $page->find('css', '#sylius_user_change_password_newPassword_first')?->setValue($newPassword);
        $page->find('css', '#sylius_user_change_password_newPassword_second')?->setValue($newPassword);
        $page->find('css', 'form button[type="submit"]')?->click();
    }

    /**
     * @Then administrator :email should not be forced to change their password anymore
     */
    public function administratorShouldNotBeForcedToChangePasswordAnymore(string $email): void
    {
        $this->entityManager->clear();
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($adminUser, PasswordExpirationAdminUserInterface::class);
        Assert::false(
            $adminUser->isForcePasswordChange(),
            sprintf('Expected administrator "%s" to have forcePasswordChange = false.', $email),
        );
    }
}
