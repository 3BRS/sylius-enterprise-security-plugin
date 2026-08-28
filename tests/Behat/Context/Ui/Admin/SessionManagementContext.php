<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Context\Ui\Admin\Helper\SecurePasswordTrait;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserSessionRepositoryInterface;
use Webmozart\Assert\Assert;

class SessionManagementContext implements Context
{
    use SecurePasswordTrait;

    /** @param UserRepositoryInterface<UserInterface> $adminUserRepository */
    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserSessionRepositoryInterface $sessionRepository,
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
     * @When I sign in to the admin panel with email :email and password :password
     */
    public function iSignInToTheAdminPanel(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_admin_login'));
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $this->retrieveSecurePassword($password));
        $page->pressButton('Login');
    }

    /**
     * @Then a login notification email should have been sent to :email
     */
    public function aLoginNotificationEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmail(Emails::LOGIN_NOTIFICATION, $email),
            sprintf('Expected login notification email for "%s", none was sent.', $email),
        );
    }

    /**
     * @Then no login notification email should have been sent to :email
     */
    public function noLoginNotificationEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmail(Emails::LOGIN_NOTIFICATION, $email),
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
     * @When I sign out from the admin panel
     */
    public function iSignOutFromTheAdminPanel(): void
    {
        $this->session->visit($this->router->generate('sylius_admin_logout'));
    }

    /**
     * @When I visit the admin active sessions page
     */
    public function iVisitTheAdminActiveSessionsPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_sessions'));
    }

    /**
     * @Then I should see exactly :count active admin session(s)
     */
    public function iShouldSeeExactlyActiveAdminSessions(int $count): void
    {
        $page = $this->session->getPage();
        $items = $page->findAll('css', '[data-test-three-brs-session]');
        Assert::count($items, $count, sprintf('Expected %d active admin sessions, got %d.', $count, count($items)));
    }

    /**
     * @Then I should see my current admin session marker
     */
    public function iShouldSeeMyCurrentAdminSessionMarker(): void
    {
        $page = $this->session->getPage();
        $marker = $page->find('css', '[data-test-three-brs-session-current]');
        Assert::notNull($marker, 'Expected current admin session marker to be visible.');
    }

    /**
     * @Given the admin :email has another active session :sessionId
     */
    public function theAdminHasAnotherActiveSession(string $email, string $sessionId): void
    {
        $user = $this->findAdminUser($email);

        $record = new AdminUserSession();
        $record->setAdminUser($user);
        $record->setSessionId($sessionId);
        $record->setUserAgent('Mozilla/5.0 (Other Admin)');
        $record->setIpAddress('203.0.113.20');
        $this->entityManager->persist($record);
        $this->entityManager->flush();
    }

    /**
     * @When I revoke the other admin session :sessionId
     */
    public function iRevokeTheOtherAdminSession(string $sessionId): void
    {
        $record = $this->sessionRepository->findOneBySessionId($sessionId);
        Assert::notNull($record, sprintf('Admin session "%s" not found.', $sessionId));

        $page = $this->session->getPage();
        $modalId = 'three-brs-admin-session-revoke-modal-' . $record->getId();
        $button = $page->find('css', sprintf('[data-test-three-brs-modal-confirm="%s"]', $modalId));
        Assert::notNull($button, sprintf('Revoke confirm button for admin session "%s" not found in modal.', $sessionId));
        $button->click();
    }

    /**
     * @When I revoke all other admin sessions
     */
    public function iRevokeAllOtherAdminSessions(): void
    {
        $page = $this->session->getPage();
        $button = $page->find('css', '[data-test-three-brs-modal-confirm="three-brs-admin-session-revoke-others-modal"]');
        Assert::notNull($button, 'Revoke-other-sessions confirm button not found in modal.');
        $button->click();
    }

    /**
     * @Then the admin session :sessionId should be revoked
     */
    public function theAdminSessionShouldBeRevoked(string $sessionId): void
    {
        $this->entityManager->clear();
        $record = $this->sessionRepository->findOneBySessionId($sessionId);
        Assert::notNull($record, sprintf('Admin session "%s" not found.', $sessionId));
        Assert::true($record->isRevoked(), sprintf('Admin session "%s" was not revoked.', $sessionId));
    }

    /**
     * @When the current admin session for :email is revoked externally
     */
    public function theCurrentAdminSessionForIsRevokedExternally(string $email): void
    {
        $user = $this->findAdminUser($email);
        $sessions = $this->sessionRepository->findActiveForAdminUser($user);
        Assert::notEmpty($sessions, sprintf('No active sessions found for admin "%s".', $email));

        foreach ($sessions as $record) {
            $record->setRevokedAt(new DateTimeImmutable());
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @Then I should be redirected to the admin login page
     */
    public function iShouldBeRedirectedToTheAdminLoginPage(): void
    {
        $url = (string) $this->session->getCurrentUrl();
        Assert::contains($url, '/admin/login', sprintf('Expected to land on the admin login page, got "%s".', $url));
    }

    protected function findAdminUser(string $email): AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($user, AdminUserInterface::class, sprintf('Admin "%s" not found.', $email));

        return $user;
    }
}
