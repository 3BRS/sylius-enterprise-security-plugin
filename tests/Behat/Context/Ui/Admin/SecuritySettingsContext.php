<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use Webmozart\Assert\Assert;

class SecuritySettingsContext implements Context
{
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected SettingsProviderInterface $settingsProvider,
        protected SettingsWriterInterface $settingsWriter,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Read the row itself rather than ask the provider.
     *
     * Two things would otherwise answer for the administrator's click. The provider
     * falls back to the configuration file when no row exists, so a feature the YAML
     * enables reads as enabled whether or not anything was saved. And the request
     * runs on its own entity manager, so a row it updates leaves this process holding
     * the entity it read earlier.
     */
    protected function storedSetting(string $path, SettingsScope $scope): ?string
    {
        $value = $this->entityManager->getConnection()->fetchOne(
            'SELECT value FROM three_brs_security_setting WHERE path = :path AND scope = :scope',
            ['path' => $path, 'scope' => $scope->value],
        );

        return $value === false ? null : (string) $value;
    }

    /**
     * The provider falls back to the configuration file when the table holds no row,
     * and the hook empties the table before each scenario — so a feature the YAML
     * enables reads as enabled before anything is clicked. Writing the row first is
     * what makes "the administrator turned it on" observable.
     *
     * @Given customer passkey is switched off
     */
    public function customerPasskeyIsSwitchedOff(): void
    {
        $this->settingsWriter->setMany(SettingsScope::CUSTOMER, ['passkey.enabled' => false]);
        $this->settingsWriter->flush();
        $this->settingsProvider->refresh();

        Assert::false(
            $this->settingsProvider->getBool('passkey.enabled', SettingsScope::CUSTOMER),
            'Expected customer passkey to start switched off.',
        );
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
        Assert::same(
            (int) $this->readSetting('password_policy.min_length', SettingsScope::CUSTOMER),
            $length,
        );
    }

    /**
     * @Then the customer passkey feature should be enabled
     */
    public function theCustomerPasskeyFeatureShouldBeEnabled(): void
    {
        Assert::same(
            'true',
            $this->storedSetting('passkey.enabled', SettingsScope::CUSTOMER),
            'The saved settings do not switch customer passkey on.',
        );
    }

    /**
     * @Then the customer login rate limit should be :count per minute
     */
    public function theCustomerLoginRateLimitShouldBePerMinute(int $count): void
    {
        Assert::true(
            (bool) $this->readSetting('rate_limit.login.enabled', SettingsScope::CUSTOMER),
            'Customer login rate limit is not enabled in DB.',
        );
        Assert::same(
            (int) $this->readSetting('rate_limit.login.limit', SettingsScope::CUSTOMER),
            $count,
        );
        Assert::same(
            (string) $this->readSetting('rate_limit.login.interval', SettingsScope::CUSTOMER),
            '1 minute',
        );
    }

    /**
     * @Then the customer Google OAuth should be disabled
     */
    public function theCustomerGoogleOAuthShouldBeDisabled(): void
    {
        Assert::false(
            (bool) $this->readSetting('oauth.google.enabled', SettingsScope::CUSTOMER),
            'Customer Google OAuth is still enabled in DB.',
        );
    }

    /**
     * `SettingsProvider::refresh()` drops the provider's own array, but the reload
     * still goes through the repository, and Doctrine hands back the entity already
     * in its identity map. After a second save in one scenario that entity is the
     * one read before the save, so the provider reports the previous value while the
     * row on disk holds the new one. Clearing first is what makes a settings
     * assertion mean anything more than once per scenario.
     */
    protected function readSetting(string $path, SettingsScope $scope): mixed
    {
        $this->entityManager->clear();
        $this->settingsProvider->refresh();

        return $this->settingsProvider->get($path, $scope);
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
