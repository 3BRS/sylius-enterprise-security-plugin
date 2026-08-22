<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Type\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Validator\Validation;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Form\Type\Settings\OAuthAutoRegistrationPolicySettingsType;

#[CoversClass(OAuthAutoRegistrationPolicySettingsType::class)]
class OAuthAutoRegistrationPolicySettingsTypeTest extends FormIntegrationTestCase
{
    public function testAcceptsValidDomainsAndParsesThemIntoAList(): void
    {
        $form = $this->createForm();
        $form->submit(['auto_register_allowed_email_domains' => "example.com\nfoo.org"]);

        $field = $form->get('auto_register_allowed_email_domains');
        self::assertTrue($form->isSynchronized());
        self::assertCount(0, $field->getErrors());
        self::assertSame(['example.com', 'foo.org'], $field->getData());
    }

    public function testRejectsAMalformedDomain(): void
    {
        $form = $this->createForm();
        $form->submit(['auto_register_allowed_email_domains' => "example.com\n@asd.com"]);

        self::assertGreaterThan(0, $form->get('auto_register_allowed_email_domains')->getErrors()->count());
    }

    public function testRejectsADomainWithoutTld(): void
    {
        $form = $this->createForm();
        $form->submit(['auto_register_allowed_email_domains' => 'asd']);

        self::assertGreaterThan(0, $form->get('auto_register_allowed_email_domains')->getErrors()->count());
    }

    public function testRejectsMoreThanTheAllowedNumberOfDomains(): void
    {
        $domains = [];
        for ($i = 0; $i <= 100; ++$i) {
            $domains[] = 'domain' . $i . '.com';
        }

        $form = $this->createForm();
        $form->submit(['auto_register_allowed_email_domains' => implode("\n", $domains)]);

        $errors = $form->get('auto_register_allowed_email_domains')->getErrors();
        self::assertGreaterThan(0, $errors->count());
        self::assertSame('three_brs.oauth_policy.too_many_domains', $errors[0]->getMessage());
    }

    public function testRejectsAnOverlongDomain(): void
    {
        // 25 valid 10-char labels + ".com" = 278 chars: every label passes the
        // shape regex, but the total exceeds the 253-char length cap.
        $domain = implode('.', array_fill(0, 25, 'abcdefghij')) . '.com';

        $form = $this->createForm();
        $form->submit(['auto_register_allowed_email_domains' => $domain]);

        self::assertGreaterThan(0, $form->get('auto_register_allowed_email_domains')->getErrors()->count());
    }

    /**
     * @return FormInterface<mixed>
     */
    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableLocaleProvider(): iterable
    {
        // Reaches AdminUser.locale_code, a varchar(12), during an OAuth callback whose
        // only catch is for OAuthProviderException.
        yield 'longer than the column' => ['English (United States)'];
        yield 'not a locale at all' => ['not-a-locale'];
    }

    #[DataProvider('unusableLocaleProvider')]
    public function testRejectsALocaleTheColumnCannotHold(string $locale): void
    {
        $form = $this->createForm(includeDefaultLocale: true);
        $form->submit(['default_locale' => $locale, 'auto_register_allowed_email_domains' => '']);

        self::assertGreaterThan(0, $form->get('default_locale')->getErrors()->count());
    }

    public function testAcceptsAnOrdinaryLocale(): void
    {
        $form = $this->createForm(includeDefaultLocale: true);
        $form->submit(['default_locale' => 'en_US', 'auto_register_allowed_email_domains' => '']);

        self::assertCount(0, $form->get('default_locale')->getErrors());
    }

    protected function createForm(bool $includeDefaultLocale = false): FormInterface
    {
        return $this->factory->create(OAuthAutoRegistrationPolicySettingsType::class, null, [
            'translation_prefix' => 'three_brs.ui.security_settings.oauth_customer_policy',
            'include_default_locale' => $includeDefaultLocale,
        ]);
    }

    /** @return object[] */
    protected function getExtensions(): array
    {
        // The domain checks below raise their errors from a POST_SUBMIT listener, so
        // they pass without this; the locale field is guarded by constraints, which
        // need the validator wired in.
        return [new ValidatorExtension(Validation::createValidator())];
    }
}
