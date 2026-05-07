<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Context\Ui\Admin\Helper\SecurePasswordTrait;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use Webmozart\Assert\Assert;

class SessionManagementContext implements Context
{
    use SecurePasswordTrait;

    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerSessionRepositoryInterface $sessionRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
        protected SpySender $spySender,
        protected SharedStorageInterface $sharedStorage,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
    }

    /**
     * @When I sign in with email :email and password :password
     */
    public function iSignInWithEmailAndPassword(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_shop_login', ['_locale' => 'en_US']));
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        $page->pressButton('Login');
    }

    /**
     * @When I visit my active sessions page
     */
    public function iVisitMyActiveSessionsPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_shop_sessions', ['_locale' => 'en_US']));
    }

    /**
     * @Then I should see exactly :count active session(s)
     */
    public function iShouldSeeExactlyActiveSessions(int $count): void
    {
        $page = $this->session->getPage();
        $items = $page->findAll('css', '[data-test-three-brs-session]');
        Assert::count($items, $count, sprintf('Expected %d active sessions, got %d.', $count, count($items)));
    }

    /**
     * @Then I should see my current session marker
     */
    public function iShouldSeeMyCurrentSessionMarker(): void
    {
        $page = $this->session->getPage();
        $marker = $page->find('css', '[data-test-three-brs-session-current]');
        Assert::notNull($marker, 'Expected current session marker to be visible.');
    }

    /**
     * @Then a login notification email should have been sent to :email
     */
    public function aLoginNotificationEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmailToRecipient($email),
            sprintf('Expected login notification email for "%s", none was sent.', $email),
        );
    }

    /**
     * @Then no login notification email should have been sent to :email
     */
    public function noLoginNotificationEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmailToRecipient($email),
            sprintf('Expected no login notification email for "%s", but one was sent.', $email),
        );
    }

    /**
     * @When login notification emails are cleared again
     */
    public function loginNotificationEmailsAreClearedAgain(): void
    {
        $this->spySender->reset();
    }

    /**
     * @When I sign out from the shop
     */
    public function iSignOutFromTheShop(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_logout', ['_locale' => 'en_US']));
    }

    /**
     * @Given the customer :email has another active session :sessionId
     */
    public function theCustomerHasAnotherActiveSession(string $email, string $sessionId): void
    {
        $user = $this->findShopUser($email);

        $record = new CustomerSession();
        $record->setShopUser($user);
        $record->setSessionId($sessionId);
        $record->setUserAgent('Mozilla/5.0 (Other)');
        $record->setIpAddress('203.0.113.10');
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    /**
     * @When I revoke the other shop session :sessionId
     */
    public function iRevokeTheOtherShopSession(string $sessionId): void
    {
        $record = $this->sessionRepository->findOneBySessionId($sessionId);
        Assert::notNull($record, sprintf('Session "%s" not found.', $sessionId));

        $page = $this->session->getPage();
        $button = $page->find('css', sprintf('[data-test-three-brs-revoke-session="%d"]', $record->getId()));
        Assert::notNull($button, sprintf('Revoke button for session "%s" not found.', $sessionId));
        $button->click();
    }

    /**
     * @When I revoke all other shop sessions
     */
    public function iRevokeAllOtherShopSessions(): void
    {
        $page = $this->session->getPage();
        $button = $page->find('css', '[data-test-three-brs-revoke-other-sessions]');
        Assert::notNull($button, 'Revoke-other-sessions button not found.');
        $button->click();
    }

    /**
     * @Then the shop session :sessionId should be revoked
     */
    public function theShopSessionShouldBeRevoked(string $sessionId): void
    {
        $this->entityManager->clear();
        $record = $this->sessionRepository->findOneBySessionId($sessionId);
        Assert::notNull($record, sprintf('Session "%s" not found.', $sessionId));
        Assert::true($record->isRevoked(), sprintf('Session "%s" was not revoked.', $sessionId));
    }

    /**
     * @When the current shop session for :email is revoked externally
     */
    public function theCurrentShopSessionForIsRevokedExternally(string $email): void
    {
        $user = $this->findShopUser($email);
        $sessions = $this->sessionRepository->findActiveForShopUser($user);
        Assert::notEmpty($sessions, sprintf('No active sessions found for "%s".', $email));

        foreach ($sessions as $record) {
            $record->setRevokedAt(new DateTimeImmutable());
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @Then I should be redirected to the shop login page
     */
    public function iShouldBeRedirectedToTheShopLoginPage(): void
    {
        $url = (string) $this->session->getCurrentUrl();
        Assert::contains($url, '/login', sprintf('Expected to land on the shop login page, got "%s".', $url));
    }

    protected function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));
        $user = $customer->getUser();
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }
}
