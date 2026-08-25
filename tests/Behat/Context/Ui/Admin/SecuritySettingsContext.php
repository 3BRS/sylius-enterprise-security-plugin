<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
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
        $form = $this->getSettingsForm();

        $field = $form->find('css', '#three_brs_security_settings_password_policy_min_length');
        Assert::notNull($field, 'min_length field not found.');
        $field->setValue((string) $length);

        $this->submit();
    }

    /**
     * @When I enable customer passkey
     */
    public function iEnableCustomerPasskey(): void
    {
        $this->iSwitchToTheScope('customer');
        $form = $this->getSettingsForm();

        $field = $form->find('css', '#three_brs_security_settings_passkey_enabled');
        Assert::notNull($field, 'enabled field not found.');
        $field->check();

        $this->submit();
    }

    /**
     * @When I tighten customer login rate limit to :count per minute
     */
    public function iTightenCustomerLoginRateLimitToPerMinute(int $count): void
    {
        $this->iSwitchToTheScope('customer');
        $form = $this->getSettingsForm();

        $form->find('css', '#three_brs_security_settings_rate_limit_login_enabled')?->check();

        $limitField = $form->find('css', '#three_brs_security_settings_rate_limit_login_limit');
        Assert::notNull($limitField, 'login_limit field not found.');
        $limitField->setValue((string) $count);

        $intervalField = $form->find('css', '#three_brs_security_settings_rate_limit_login_interval');
        Assert::notNull($intervalField, 'login_interval field not found.');
        $intervalField->setValue('1 minute');

        $this->submit();
    }

    /**
     * @When I disable customer Google OAuth
     */
    public function iDisableCustomerGoogleOAuth(): void
    {
        $this->iSwitchToTheScope('customer');
        $form = $this->getSettingsForm();

        $field = $form->find('css', '#three_brs_security_settings_oauth_google_enabled');
        Assert::notNull($field, 'google_enabled field not found.');
        $field->uncheck();

        $this->submit();
    }

    /**
     * @When I enable customer account lockout with max attempts :count
     */
    public function iEnableCustomerAccountLockoutWithMaxAttempts(int $count): void
    {
        $this->iSwitchToTheScope('customer');
        $form = $this->getSettingsForm();

        $form->find('css', '#three_brs_security_settings_account_lockout_enabled')?->check();
        $maxField = $form->find('css', '#three_brs_security_settings_account_lockout_max_attempts');
        Assert::notNull($maxField, 'max_attempts field not found.');
        $maxField->setValue((string) $count);

        $this->submit();
    }

    /**
     * @When customer :email attempts :count failed sign-ins
     */
    public function customerAttemptsFailedSignIns(string $email, int $count): void
    {
        $loginUrl = $this->router->generate('sylius_shop_login', ['_locale' => 'en_US'], UrlGeneratorInterface::ABSOLUTE_URL);
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

    /**
     * @Then the customer login rate limit should be :count per minute
     */
    public function theCustomerLoginRateLimitShouldBePerMinute(int $count): void
    {
        $this->settingsProvider->refresh();
        Assert::true(
            $this->settingsProvider->getBool('rate_limit.login.enabled', SettingsScope::CUSTOMER),
            'Customer login rate limit is not enabled in DB.',
        );
        Assert::same(
            $this->settingsProvider->getInt('rate_limit.login.limit', SettingsScope::CUSTOMER),
            $count,
        );
        Assert::same(
            $this->settingsProvider->getString('rate_limit.login.interval', SettingsScope::CUSTOMER),
            '1 minute',
        );
    }

    /**
     * @Then the customer Google OAuth should be disabled
     */
    public function theCustomerGoogleOAuthShouldBeDisabled(): void
    {
        $this->settingsProvider->refresh();
        Assert::false(
            $this->settingsProvider->getBool('oauth.google.enabled', SettingsScope::CUSTOMER),
            'Customer Google OAuth is still enabled in DB.',
        );
    }

    protected function getSettingsForm(): NodeElement
    {
        $form = $this->session->getPage()->find('css', '#three-brs-security-settings-form');
        Assert::notNull($form, 'Security settings form not found.');

        return $form;
    }

    protected function submit(): void
    {
        $button = $this->session->getPage()->find('css', '[data-test-three-brs-security-settings-save]');
        Assert::notNull($button, 'Global save button not found.');
        $button->click();
    }
}
