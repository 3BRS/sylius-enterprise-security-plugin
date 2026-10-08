<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSessionRepositoryInterface;
use Webmozart\Assert\Assert;

class CustomerManagementContext implements Context
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     */
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerSessionRepositoryInterface $sessionRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the customer :email has an active session from :ipAddress
     */
    public function theCustomerHasActiveSessionFrom(string $email, string $ipAddress): void
    {
        $shopUser = $this->loadShopUser($email);

        $session = new CustomerSession();
        $session->setShopUser($shopUser);
        $session->setSessionId('behat-session-' . uniqid('', true));
        $session->setUserAgent('Mozilla/5.0 (Test)');
        $session->setIpAddress($ipAddress);

        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    /**
     * @Given the customer :email has a session from :ipAddress last active :days days ago
     */
    public function theCustomerHasASessionFromLastActiveDaysAgo(string $email, string $ipAddress, int $days): void
    {
        $session = new CustomerSession();
        $session->setShopUser($this->loadShopUser($email));
        $session->setSessionId('behat-session-' . uniqid('', true));
        $session->setUserAgent('Mozilla/5.0 (Test)');
        $session->setIpAddress($ipAddress);
        $session->setLastActivityAt(new \DateTimeImmutable(sprintf('-%d days', $days)));

        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    /**
     * @When I open the customer detail page for :email
     */
    public function iOpenTheCustomerDetailPageFor(string $email): void
    {
        $customer = $this->loadCustomer($email);
        $this->session->visit($this->router->generate(
            'sylius_admin_customer_show',
            ['id' => $customer->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    /**
     * @When I force a password reset for customer :email
     */
    public function iForcePasswordResetFor(string $email): void
    {
        $this->iOpenTheCustomerDetailPageFor($email);
        $this->submitConfirmationModal('three-brs-customer-modal-force-reset');
    }

    /**
     * @When I block customer :email from the admin panel
     */
    public function iBlockCustomerFromTheAdminPanel(string $email): void
    {
        $this->iOpenTheCustomerDetailPageFor($email);
        $this->submitConfirmationModal('three-brs-customer-modal-block');
    }

    /**
     * @When I revoke all sessions for customer :email
     */
    public function iRevokeAllSessionsFor(string $email): void
    {
        $this->iOpenTheCustomerDetailPageFor($email);
        $this->submitConfirmationModal('three-brs-customer-modal-revoke-all');
    }

    /**
     * @When I revoke the first active session for customer :email
     */
    public function iRevokeFirstSessionFor(string $email): void
    {
        $this->iOpenTheCustomerDetailPageFor($email);
        $shopUser = $this->loadShopUser($email);
        $sessions = $this->sessionRepository->findActiveForShopUser($shopUser);
        Assert::notEmpty($sessions);

        $this->submitConfirmationModal('three-brs-customer-modal-revoke-session-' . $sessions[0]->getId());
    }

    /**
     * Modals are rendered as plain HTML in the DOM. The headless Behat driver doesn't
     * execute the Bootstrap JS that toggles visibility, but the form inside the modal
     * is fully posted by clicking the submit button directly — that's what real users
     * trigger after confirming, so we exercise the same path.
     */
    protected function submitConfirmationModal(string $modalId): void
    {
        $button = $this->session->getPage()->find('css', sprintf(
            '[data-test-three-brs-modal-confirm="%s"]',
            $modalId,
        ));
        Assert::notNull($button, sprintf('Confirmation modal "%s" not found.', $modalId));
        $button->click();
    }

    /**
     * @Then customer :email should be required to change their password on next sign-in
     */
    public function customerShouldBeRequiredToChangePassword(string $email): void
    {
        $this->entityManager->clear();
        $shopUser = $this->loadShopUser($email);
        Assert::isInstanceOf($shopUser, PasswordExpirationShopUserInterface::class);

        Assert::true(
            $shopUser->isForcePasswordChange(),
            sprintf('forcePasswordChange flag is not set on "%s".', $email),
        );
    }

    /**
     * @Then customer :email should be blocked
     */
    public function customerShouldBeBlocked(string $email): void
    {
        $this->entityManager->clear();
        $shopUser = $this->loadShopUser($email);

        Assert::false(
            $shopUser->isEnabled(),
            sprintf('Customer "%s" is still enabled.', $email),
        );
    }

    /**
     * @Then customer :email should have :count active session(s)
     */
    public function customerShouldHaveActiveSessions(string $email, int $count): void
    {
        $this->entityManager->clear();
        $shopUser = $this->loadShopUser($email);

        Assert::count(
            $this->sessionRepository->findActiveForShopUser($shopUser),
            $count,
            sprintf('Customer "%s" active session count mismatch.', $email),
        );
    }

    /**
     * @Then every session of customer :email should be revoked
     */
    public function everySessionOfCustomerShouldBeRevoked(string $email): void
    {
        $this->entityManager->clear();
        $sessions = $this->sessionRepository->findAllForShopUser($this->loadShopUser($email));
        Assert::notEmpty($sessions, sprintf('Customer "%s" has no sessions.', $email));

        foreach ($sessions as $session) {
            Assert::true($session->isRevoked(), sprintf('The session from "%s" was not revoked.', (string) $session->getIpAddress()));
        }
    }

    /**
     * @Then I should see no active sessions of the customer
     */
    public function iShouldSeeNoActiveSessionsOfTheCustomer(): void
    {
        Assert::false(
            $this->session->getPage()->has('css', '[data-test-three-brs-customer-security-active-sessions]'),
            'The active sessions table is rendered.',
        );
    }

    /**
     * @Then I should not be offered to sign the customer out of all sessions
     */
    public function iShouldNotBeOfferedToSignTheCustomerOutOfAllSessions(): void
    {
        Assert::false(
            $this->session->getPage()->has('css', '[data-test-three-brs-customer-security-revoke-all]'),
            'The button that signs the customer out of all sessions is rendered.',
        );
    }

    /**
     * @Then the login history should show the session from :ipAddress as :status
     */
    public function theLoginHistoryShouldShowTheSessionFromAs(string $ipAddress, string $status): void
    {
        $rows = array_filter(
            $this->session->getPage()->findAll('css', '[data-test-three-brs-customer-security-login-history] tbody tr'),
            static fn (NodeElement $row): bool => str_contains($row->getText(), $ipAddress),
        );
        Assert::count($rows, 1, sprintf('Expected one login history row from "%s".', $ipAddress));

        $row = array_values($rows)[0];
        Assert::true(
            $row->has('css', sprintf('[data-test-three-brs-customer-security-session-status="%s"]', $status)),
            sprintf('The session from "%s" is not shown as %s.', $ipAddress, $status),
        );
    }

    /**
     * @Then I should see the customer security section
     */
    public function iShouldSeeTheCustomerSecuritySection(): void
    {
        Assert::true(
            $this->session->getPage()->has('css', '[data-test-three-brs-customer-security]'),
            'Customer security section is not rendered on the customer detail page.',
        );
    }

    /**
     * @Then I should see the login history table
     */
    public function iShouldSeeTheLoginHistoryTable(): void
    {
        Assert::true(
            $this->session->getPage()->has('css', '[data-test-three-brs-customer-security-login-history]'),
            'Login history table is not rendered.',
        );
    }

    protected function loadCustomer(string $email): CustomerInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);

        return $customer;
    }

    protected function loadShopUser(string $email): ShopUserInterface
    {
        $customer = $this->loadCustomer($email);
        $shopUser = $customer->getUser();
        Assert::isInstanceOf($shopUser, ShopUserInterface::class);

        return $shopUser;
    }
}
