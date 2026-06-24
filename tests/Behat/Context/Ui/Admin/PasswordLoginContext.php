<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use Webmozart\Assert\Assert;

class PasswordLoginContext implements Context
{
    protected const ADMIN_PASSWORD_SELECTOR = 'input[name="sylius_admin_admin_user[plainPassword]"]';

    protected const CUSTOMER_PASSWORD_SELECTOR = 'input[name="sylius_admin_customer[user][plainPassword]"]';

    /**
     * @param UserRepositoryInterface<UserInterface> $adminUserRepository
     */
    public function __construct(
        protected Session $session,
        protected SettingsWriterInterface $settingsWriter,
        protected SettingsProviderInterface $settingsProvider,
        protected UrlGeneratorInterface $router,
        protected CustomerRepositoryInterface $customerRepository,
        protected UserRepositoryInterface $adminUserRepository,
    ) {
    }

    /**
     * Password login defaults to ON for both scopes; reset before each scenario so a
     * previous "disabled" scenario cannot bleed into the next one.
     *
     * @BeforeScenario
     */
    public function resetPasswordLogin(): void
    {
        $this->setPasswordLogin(SettingsScope::ADMIN, true);
        $this->setPasswordLogin(SettingsScope::CUSTOMER, true);
    }

    /**
     * @Given password login is disabled for admins
     */
    public function passwordLoginIsDisabledForAdmins(): void
    {
        $this->setPasswordLogin(SettingsScope::ADMIN, false);
    }

    /**
     * @Given password login is disabled for customers
     */
    public function passwordLoginIsDisabledForCustomers(): void
    {
        $this->setPasswordLogin(SettingsScope::CUSTOMER, false);
    }

    /**
     * @When I open the admin user edit page for :email
     */
    public function iOpenTheAdminUserEditPage(string $email): void
    {
        $admin = $this->adminUserRepository->findOneByEmail($email);
        Assert::isInstanceOf($admin, UserInterface::class);

        $this->session->visit($this->router->generate(
            'sylius_admin_admin_user_update',
            ['id' => $admin->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    /**
     * @When I open the customer edit page for :email
     */
    public function iOpenTheCustomerEditPage(string $email): void
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);

        $this->session->visit($this->router->generate(
            'sylius_admin_customer_update',
            ['id' => $customer->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    /**
     * @Then the admin-user password field should be visible
     */
    public function theAdminPasswordFieldShouldBeVisible(): void
    {
        Assert::notNull(
            $this->session->getPage()->find('css', self::ADMIN_PASSWORD_SELECTOR),
            'Expected the admin-user password field to be present.',
        );
    }

    /**
     * @Then the admin-user password field should be hidden
     */
    public function theAdminPasswordFieldShouldBeHidden(): void
    {
        Assert::null(
            $this->session->getPage()->find('css', self::ADMIN_PASSWORD_SELECTOR),
            'Expected the admin-user password field to be absent.',
        );
    }

    /**
     * @Then the customer password field should be visible
     */
    public function theCustomerPasswordFieldShouldBeVisible(): void
    {
        Assert::notNull(
            $this->session->getPage()->find('css', self::CUSTOMER_PASSWORD_SELECTOR),
            'Expected the customer password field to be present.',
        );
    }

    /**
     * @Then the customer password field should be hidden
     */
    public function theCustomerPasswordFieldShouldBeHidden(): void
    {
        Assert::null(
            $this->session->getPage()->find('css', self::CUSTOMER_PASSWORD_SELECTOR),
            'Expected the customer password field to be absent.',
        );
    }

    protected function setPasswordLogin(SettingsScope $scope, bool $enabled): void
    {
        $this->settingsWriter->set('password_login.enabled', $scope, $enabled);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }
}
