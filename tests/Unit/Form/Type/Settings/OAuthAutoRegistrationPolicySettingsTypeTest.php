<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Form\Type\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
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
    protected function createForm(): FormInterface
    {
        return $this->factory->create(OAuthAutoRegistrationPolicySettingsType::class, null, [
            'translation_prefix' => 'three_brs.ui.security_settings.oauth_customer_policy',
            'include_default_locale' => false,
        ]);
    }
}
