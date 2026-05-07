<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsProviderInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SettingsScope;
use Webmozart\Assert\Assert;

class SecuritySettingsContext implements Context
{
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected SettingsProviderInterface $settingsProvider,
    ) {
    }

    /**
     * @When I open the security settings page
     */
    public function iOpenTheSecuritySettingsPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_security_settings_index', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @When I switch to the :scope scope
     */
    public function iSwitchToTheScope(string $scope): void
    {
        $this->session->visit($this->router->generate(
            'three_brs_admin_security_settings_index',
            ['scope' => $scope],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    /**
     * @When I change the customer minimum password length to :length
     */
    public function iChangeTheCustomerMinimumPasswordLengthTo(int $length): void
    {
        $this->iSwitchToTheScope('customer');
        $page = $this->session->getPage();
        $form = $page->find('css', '[data-test-three-brs-security-settings-tab="password_policy"] form');
        Assert::notNull($form, 'Password policy form not found.');

        $field = $form->find('css', '#three_brs_password_policy_settings_min_length');
        Assert::notNull($field, 'min_length field not found.');
        $field->setValue((string) $length);

        $form->find('css', '[data-test-three-brs-security-settings-save="password_policy"]')?->click();
    }

    /**
     * @When I enable customer passkey
     */
    public function iEnableCustomerPasskey(): void
    {
        $this->iSwitchToTheScope('customer');
        $page = $this->session->getPage();
        $form = $page->find('css', '[data-test-three-brs-security-settings-tab="passkey"] form');
        Assert::notNull($form, 'Passkey form not found.');

        $field = $form->find('css', '#three_brs_passkey_settings_enabled');
        Assert::notNull($field, 'enabled field not found.');
        $field->check();

        $form->find('css', '[data-test-three-brs-security-settings-save="passkey"]')?->click();
    }

    /**
     * @When I enable customer account lockout with max attempts :count
     */
    public function iEnableCustomerAccountLockoutWithMaxAttempts(int $count): void
    {
        $this->iSwitchToTheScope('customer');
        $page = $this->session->getPage();
        $form = $page->find('css', '[data-test-three-brs-security-settings-tab="account_lockout"] form');
        Assert::notNull($form, 'Account lockout form not found.');

        $form->find('css', '#three_brs_account_lockout_settings_enabled')?->check();
        $maxField = $form->find('css', '#three_brs_account_lockout_settings_max_attempts');
        Assert::notNull($maxField, 'max_attempts field not found.');
        $maxField->setValue((string) $count);

        $form->find('css', '[data-test-three-brs-security-settings-save="account_lockout"]')?->click();
    }

    /**
     * @When customer :email attempts :count failed sign-ins
     */
    public function customerAttemptsFailedSignIns(string $email, int $count): void
    {
        $loginUrl = $this->router->generate('sylius_shop_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        for ($i = 0; $i < $count; $i++) {
            $this->session->visit($loginUrl);
            $page = $this->session->getPage();
            $page->fillField('_username', $email);
            $page->fillField('_password', 'WrongPassword!');
            $page->pressButton('Login');
        }
    }

    /**
     * @Then I should see the security settings configuration page
     */
    public function iShouldSeeTheSecuritySettingsConfigurationPage(): void
    {
        Assert::true(
            $this->session->getPage()->has('css', '[data-test-three-brs-security-settings-scope]'),
            'Security settings configuration page not rendered.',
        );
    }

    /**
     * @Then I should see the :sectionLabel section
     */
    public function iShouldSeeTheSection(string $sectionLabel): void
    {
        Assert::true(
            $this->session->getPage()->hasContent($sectionLabel),
            sprintf('Section "%s" not found on the page.', $sectionLabel),
        );
    }

    /**
     * @Then the customer password minimum length should be :length
     */
    public function theCustomerPasswordMinimumLengthShouldBe(int $length): void
    {
        $this->settingsProvider->refresh();
        Assert::same(
            $this->settingsProvider->getInt('password_policy.min_length', SettingsScope::CUSTOMER),
            $length,
        );
    }

    /**
     * @Then the customer passkey feature should be enabled
     */
    public function theCustomerPasskeyFeatureShouldBeEnabled(): void
    {
        $this->settingsProvider->refresh();
        Assert::true(
            $this->settingsProvider->getBool('passkey.enabled', SettingsScope::CUSTOMER),
            'Customer passkey feature is not enabled in DB.',
        );
    }
}
