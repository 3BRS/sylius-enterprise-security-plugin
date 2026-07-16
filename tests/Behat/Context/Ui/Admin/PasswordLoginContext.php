<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Context\Ui\Admin\Helper\SecurePasswordTrait;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
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
    use SecurePasswordTrait;

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
        protected SharedStorageInterface $sharedStorage,
        protected EntityManagerInterface $entityManager,
    ) {
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

    /**
     * @When I enable the account of customer :email
     */
    public function iEnableTheAccountOfCustomer(string $email): void
    {
        $this->iOpenTheCustomerEditPage($email);

        $this->session->getPage()->checkField('sylius_admin_customer[user][enabled]');
        $this->submitForm('sylius_admin_customer');
    }

    /**
     * Submits the customer form with an email that already belongs to somebody else, so the save is
     * refused and the form comes back — the render on which Sylius has already swapped the nested
     * user form for its own type, which is where the password field would otherwise reappear.
     *
     * @When I try to enable the account of customer :email using the email of :takenEmail
     */
    public function iTryToEnableTheAccountOfCustomerUsingATakenEmail(string $email, string $takenEmail): void
    {
        $this->iOpenTheCustomerEditPage($email);

        $page = $this->session->getPage();
        $page->fillField('sylius_admin_customer[email]', $takenEmail);
        $page->checkField('sylius_admin_customer[user][enabled]');

        $this->submitForm('sylius_admin_customer');
    }

    /**
     * @Then the customer form should come back with an error
     */
    public function theCustomerFormShouldComeBackWithAnError(): void
    {
        $page = $this->session->getPage();

        Assert::notNull(
            $page->find('css', 'form[id="sylius_admin_customer"]'),
            'Expected the customer form to be rendered again.',
        );
        Assert::notNull(
            $page->find('css', '.invalid-feedback, .alert-danger'),
            'Expected the re-rendered customer form to show a validation error.',
        );
    }

    /**
     * @Then customer :email should have an account without a password
     */
    public function customerShouldHaveAnAccountWithoutAPassword(string $email): void
    {
        // The account was created by the request the browser made, in its own entity manager.
        $this->entityManager->clear();

        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);

        $user = $customer->getUser();
        Assert::isInstanceOf($user, ShopUserInterface::class, 'Expected the customer to have an account.');
        Assert::true($user->isEnabled(), 'Expected the account to be enabled.');
        Assert::null($user->getPassword(), 'Expected the account to be stored without a password.');
    }

    /**
     * @When I create an administrator :email
     */
    public function iCreateAnAdministrator(string $email): void
    {
        $this->session->visit($this->router->generate(
            'sylius_admin_admin_user_create',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));

        $page = $this->session->getPage();
        $page->fillField('sylius_admin_admin_user[firstName]', 'New');
        $page->fillField('sylius_admin_admin_user[lastName]', 'Administrator');
        $page->fillField('sylius_admin_admin_user[email]', $email);
        $page->fillField('sylius_admin_admin_user[username]', $email);
        $page->selectFieldOption('sylius_admin_admin_user[localeCode]', 'en_US');
        $page->checkField('sylius_admin_admin_user[enabled]');

        $this->submitForm('sylius_admin_admin_user');
    }

    /**
     * @Then administrator :email should exist without a password
     */
    public function administratorShouldExistWithoutAPassword(string $email): void
    {
        $this->entityManager->clear();

        $admin = $this->adminUserRepository->findOneByEmail($email);
        Assert::isInstanceOf($admin, UserInterface::class, 'Expected the administrator to be created.');
        Assert::null($admin->getPassword(), 'Expected the administrator to be stored without a password.');
    }

    /**
     * @When I visit the admin login page
     */
    public function iVisitTheAdminLoginPage(): void
    {
        $this->session->visit($this->router->generate('sylius_admin_login'));
    }

    /**
     * The login form — and its CSRF token — is only rendered while admin password login is on,
     * so this submits the form already loaded on the current page. A scenario can therefore
     * load it while the switch is on and then flip the switch before submitting, which
     * exercises the authentication-layer backstop (AdminUserPasswordLoginCheckListener) with a
     * genuine, CSRF-valid POST instead of merely proving the template hid the form.
     *
     * @When I submit the admin login form with email :email and password :password
     */
    public function iSubmitTheAdminLoginForm(string $email, string $password): void
    {
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        $page->pressButton('Login');
    }

    /** The save button lives in a separate twig hook, so the form itself is submitted instead. */
    protected function submitForm(string $formId): void
    {
        $form = $this->session->getPage()->find('css', sprintf('form[id="%s"]', $formId));
        Assert::isInstanceOf($form, NodeElement::class, sprintf('Form "%s" not found on the page.', $formId));

        $form->submit();
    }

    /**
     * @Then I should not be signed in to the admin panel
     */
    public function iShouldNotBeSignedInToTheAdminPanel(): void
    {
        Assert::contains(
            $this->session->getCurrentUrl(),
            '/login',
            'Expected to remain on the admin login page after a rejected sign-in.',
        );
    }

    protected function setPasswordLogin(SettingsScope $scope, bool $enabled): void
    {
        $this->settingsWriter->set('password_login.enabled', $scope, $enabled);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();
    }
}
