<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Functional;

use DateTimeImmutable;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\ShopUser;
use Sylius\Component\Mailer\Provider\EmailProviderInterface;
use Sylius\Component\Mailer\Renderer\Adapter\AdapterInterface;
use Sylius\Component\Mailer\Renderer\RenderedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\EnterpriseSecurityBundle\Session\UserAgentInfo;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserLoginNotificationEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AdminUserMagicLinkEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerLoginNotificationEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\CustomerMagicLinkEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\OAuthLinkCodeEmailManager;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\PasswordChangeEmailManager;

/**
 * Behat replaces the mailer with a spy, so no scenario ever renders one of the six
 * shipped templates. A renamed payload key, a missing translation or a Twig typo
 * therefore reaches production silently — every existing assertion only says that
 * something was handed to the sender.
 *
 * Each test here drives a real email manager into the spy and renders the template
 * the plugin registers for that code with exactly the payload the manager produced.
 * That pins the two sides together: rename `magicLinkUrl` on either side and the
 * link disappears from the body.
 */
class EmailTemplateRenderingTest extends KernelTestCase
{
    protected SpySender $sender;

    protected EmailProviderInterface $emailProvider;

    protected AdapterInterface $renderer;

    protected UrlGeneratorInterface $router;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $container = self::getContainer();

        /** @var SpySender $sender */
        $sender = $container->get('sylius.email_sender');
        $this->sender = $sender;
        $this->sender->reset();

        /** @var EmailProviderInterface $emailProvider */
        $emailProvider = $container->get('sylius.email_provider');
        $this->emailProvider = $emailProvider;

        /** @var AdapterInterface $renderer */
        $renderer = $container->get('sylius.email_renderer.adapter.twig');
        $this->renderer = $renderer;

        /** @var UrlGeneratorInterface $router */
        $router = $container->get('router');
        $this->router = $router;
    }

    protected function tearDown(): void
    {
        $this->sender->reset();

        parent::tearDown();

        // Booting the Sylius kernel leaves an exception handler on the stack.
        restore_exception_handler();
    }

    public function testTheMagicLinkEmailCarriesAUsableSignInAddress(): void
    {
        $manager = new CustomerMagicLinkEmailManager($this->sender, $this->router);
        $manager->sendMagicLink($this->shopUser('customer@example.com'), 'plain-shop-token', 300);

        $rendered = $this->render(Emails::MAGIC_LINK, 'customer@example.com');

        self::assertSame('Your sign-in link', $rendered->getSubject());
        self::assertStringContainsString('/magic-link/verify/plain-shop-token', $rendered->getBody());
        self::assertStringContainsString('expire in 5 minutes', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    public function testTheAdminMagicLinkEmailPointsAtTheAdminRoute(): void
    {
        $manager = new AdminUserMagicLinkEmailManager($this->sender, $this->router);
        $manager->sendMagicLink($this->adminUser('admin@example.com'), 'plain-admin-token', 600);

        $rendered = $this->render(Emails::MAGIC_LINK, 'admin@example.com');

        self::assertStringContainsString('/admin/magic-link/verify/plain-admin-token', $rendered->getBody());
        self::assertStringContainsString('expire in 10 minutes', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    public function testTheLoginNotificationEmailShowsTheDeviceItSaw(): void
    {
        $manager = new CustomerLoginNotificationEmailManager($this->sender);
        $manager->sendNewDeviceNotification(
            $this->shopUser('customer@example.com'),
            new DateTimeImmutable('2026-03-04 05:06:07'),
            '203.0.113.10',
            'Czechia',
            'Brno',
            new UserAgentInfo('Firefox', 'Linux', 'desktop'),
        );

        $rendered = $this->render(Emails::LOGIN_NOTIFICATION, 'customer@example.com');

        self::assertSame('New sign-in to your account', $rendered->getSubject());
        self::assertStringContainsString('2026-03-04 05:06:07', $rendered->getBody());
        self::assertStringContainsString('Firefox', $rendered->getBody());
        self::assertStringContainsString('Linux', $rendered->getBody());
        self::assertStringContainsString('203.0.113.10', $rendered->getBody());
        self::assertStringContainsString('Brno, Czechia', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    /**
     * Without GeoIP and behind a proxy that strips the address, every optional
     * field arrives null — the branch that runs most often in practice.
     */
    public function testTheLoginNotificationEmailFallsBackWhenNothingIsKnown(): void
    {
        $manager = new AdminUserLoginNotificationEmailManager($this->sender);
        $manager->sendNewDeviceNotification(
            $this->adminUser('admin@example.com'),
            new DateTimeImmutable('2026-03-04 05:06:07'),
            null,
            null,
            null,
            new UserAgentInfo(null, null, null),
        );

        $rendered = $this->render(Emails::LOGIN_NOTIFICATION, 'admin@example.com');
        $body = $rendered->getBody();

        self::assertStringContainsString('Unknown', $body);
        self::assertStringNotContainsString('Location', $body, 'The location row belongs to emails that know a location.');
        $this->assertFullyTranslated($rendered);
    }

    public function testThePasswordChangedEmailStaysQuietWhenTheUserMadeTheChange(): void
    {
        $manager = new PasswordChangeEmailManager($this->sender, $this->router);
        $manager->sendPasswordChangedEmail($this->shopUser('customer@example.com'), null, true);

        $rendered = $this->render(Emails::PASSWORD_CHANGED, 'customer@example.com');

        self::assertSame('Your password has been changed', $rendered->getSubject());
        self::assertStringNotContainsString('Secure My Account', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    /**
     * The warning and the reset link are the whole point of this email whenever an
     * administrator, a reset token or a support agent changed the password.
     */
    public function testThePasswordChangedEmailWarnsWhenSomebodyElseMadeTheChange(): void
    {
        $manager = new PasswordChangeEmailManager($this->sender, $this->router);
        $manager->sendPasswordChangedEmail($this->adminUser('admin@example.com'), null, false);

        $rendered = $this->render(Emails::PASSWORD_CHANGED, 'admin@example.com');
        $body = $rendered->getBody();

        self::assertStringContainsString('your account may be compromised', $body);
        self::assertStringContainsString('Secure My Account', $body);
        self::assertStringContainsString($this->router->generate('sylius_admin_request_password_reset'), $body);
        $this->assertFullyTranslated($rendered);
    }

    public function testTheDeletionRequestEmailNamesTheDeadline(): void
    {
        $manager = new AccountDeletionEmailManager($this->sender);
        $manager->sendDeletionRequested(
            $this->customer('customer@example.com'),
            new DateTimeImmutable('2026-04-05 06:07:08'),
        );

        $rendered = $this->render(Emails::ACCOUNT_DELETION_REQUESTED, 'customer@example.com');

        self::assertSame('Your account deletion request', $rendered->getSubject());
        self::assertStringContainsString('2026-04-05 06:07', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    public function testTheDeletionCompletedEmailRenders(): void
    {
        $manager = new AccountDeletionEmailManager($this->sender);
        $manager->sendDeletionCompleted($this->customer('customer@example.com'));

        $rendered = $this->render(Emails::ACCOUNT_DELETION_COMPLETED, 'customer@example.com');

        self::assertSame('Your account has been deleted', $rendered->getSubject());
        self::assertStringContainsString('has been deleted as requested', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    public function testTheAccountLinkingEmailShowsTheCode(): void
    {
        $manager = new OAuthLinkCodeEmailManager($this->sender);
        $manager->sendLinkCode('customer@example.com', '482913', 900);

        $rendered = $this->render(Emails::OAUTH_LINK_CODE, 'customer@example.com');

        self::assertSame('Your account linking code', $rendered->getSubject());
        self::assertStringContainsString('482913', $rendered->getBody());
        self::assertStringContainsString('expire in 15 minutes', $rendered->getBody());
        $this->assertFullyTranslated($rendered);
    }

    protected function render(string $code, string $recipient): RenderedEmail
    {
        $data = $this->sender->getLastSentDataTo($code, $recipient);
        self::assertNotNull($data, sprintf(
            'The manager sent no "%s" email to "%s" (sent: %s).',
            $code,
            $recipient,
            $this->sender->describeSentEmails(),
        ));

        return $this->renderer->render($this->emailProvider->getEmail($code), $data);
    }

    /**
     * Twig echoes an unknown translation key verbatim, so a leftover `three_brs.`
     * is a message the customer would have received as-is.
     */
    protected function assertFullyTranslated(RenderedEmail $rendered): void
    {
        self::assertStringNotContainsString('three_brs.', $rendered->getSubject(), 'The subject leaks an untranslated key.');
        self::assertStringNotContainsString('three_brs.', $rendered->getBody(), 'The body leaks an untranslated key.');
    }

    protected function shopUser(string $email): ShopUser
    {
        $user = new ShopUser();
        $user->setCustomer($this->customer($email));

        return $user;
    }

    protected function customer(string $email): Customer
    {
        $customer = new Customer();
        $customer->setEmail($email);

        return $customer;
    }

    protected function adminUser(string $email): AdminUser
    {
        $user = new AdminUser();
        $user->setEmail($email);

        return $user;
    }
}
