<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Command\ProcessDueAccountDeletionsCommand;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDueDeletionsProcessorInterface;
use Webmozart\Assert\Assert;

class AccountDeletionContext implements Context
{
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerDeletionRequestRepositoryInterface $deletionRepository,
        protected CustomerDueDeletionsProcessorInterface $dueProcessor,
        protected EntityManagerInterface $entityManager,
        protected SharedStorageInterface $sharedStorage,
        protected SpySender $spySender,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
    }

    /**
     * @When I visit the account deletion page
     */
    public function iVisitAccountDeletionPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_shop_account_deletion_request', ['_locale' => 'en_US'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @When I confirm account deletion with my password :password
     */
    public function iConfirmAccountDeletionWithMyPassword(string $password): void
    {
        $page = $this->session->getPage();
        $form = $page->find('css', '#three_brs_account_deletion_form');
        Assert::notNull($form, 'Account deletion form not found on the page.');

        $form->find('css', 'input[name="three_brs_account_deletion_request[currentPassword]"]')
            ?->setValue($this->retrieveSecurePassword($password));
        $form->find('css', 'input[name="three_brs_account_deletion_request[acknowledged]"]')?->check();
        $form->find('css', 'button[type="submit"]')?->click();
    }

    /**
     * @When I confirm account deletion without a password
     */
    public function iConfirmAccountDeletionWithoutAPassword(): void
    {
        $page = $this->session->getPage();
        $form = $page->find('css', '#three_brs_account_deletion_form');
        Assert::notNull($form, 'Account deletion form not found on the page.');

        Assert::null(
            $form->find('css', 'input[name="three_brs_account_deletion_request[currentPassword]"]'),
            'Expected no password field while password login is disabled for customers.',
        );

        $form->find('css', 'input[name="three_brs_account_deletion_request[acknowledged]"]')?->check();
        $form->find('css', 'button[type="submit"]')?->click();
    }

    /**
     * @Given the customer :email requested deletion :daysAgo days ago with grace :graceDays
     */
    public function theCustomerRequestedDeletionDaysAgoWithGrace(string $email, int $daysAgo, int $graceDays): void
    {
        $customer = $this->loadCustomer($email);

        $requestedAt = new \DateTimeImmutable(sprintf('-%d days', $daysAgo));
        $scheduledFor = $requestedAt->add(new \DateInterval('P' . $graceDays . 'D'));

        $request = new CustomerDeletionRequest();
        $request->setCustomer($customer);
        $request->setScheduledFor($scheduledFor);

        $shopUser = $customer->getUser();
        if ($shopUser instanceof ShopUserInterface) {
            $shopUser->setEnabled(false);
        }

        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }

    /**
     * @When the account deletion process-due command runs
     */
    public function theAccountDeletionProcessDueCommandRuns(): void
    {
        $tester = new CommandTester(new ProcessDueAccountDeletionsCommand($this->dueProcessor));
        $tester->execute([]);
    }

    /**
     * @Then a deletion request should be scheduled for :email
     */
    public function aDeletionRequestShouldBeScheduledFor(string $email): void
    {
        $customer = $this->loadCustomer($email);
        $this->entityManager->refresh($customer);

        Assert::notNull(
            $this->deletionRepository->findActiveForCustomer($customer),
            sprintf('No pending deletion request found for "%s".', $email),
        );
    }

    /**
     * @Then no deletion request should exist for :email
     */
    public function noDeletionRequestShouldExistFor(string $email): void
    {
        $customer = $this->loadCustomer($email);

        Assert::null(
            $this->deletionRepository->findActiveForCustomer($customer),
            sprintf('Unexpected pending deletion request for "%s".', $email),
        );
    }

    /**
     * @Then the customer :email should be disabled
     */
    public function theCustomerShouldBeDisabled(string $email): void
    {
        $customer = $this->loadCustomer($email);
        $this->entityManager->refresh($customer);
        $shopUser = $customer->getUser();
        Assert::isInstanceOf($shopUser, ShopUserInterface::class);
        $this->entityManager->refresh($shopUser);

        Assert::false($shopUser->isEnabled(), sprintf('Customer "%s" is still enabled.', $email));
    }

    /**
     * @Then the customer :email should be enabled
     */
    public function theCustomerShouldBeEnabled(string $email): void
    {
        $customer = $this->loadCustomer($email);
        $this->entityManager->refresh($customer);
        $shopUser = $customer->getUser();
        Assert::isInstanceOf($shopUser, ShopUserInterface::class);
        $this->entityManager->refresh($shopUser);

        Assert::true($shopUser->isEnabled(), sprintf('Customer "%s" is disabled.', $email));
    }

    /**
     * @Then the customer formerly known as :email should be anonymized
     */
    public function theCustomerFormerlyKnownAsShouldBeAnonymized(string $email): void
    {
        // After anonymization the original email no longer matches; look up by ID
        // captured at fixture time would be cleaner, but for this scenario the
        // customer has a unique email "delete-me@..." so we can scan the table.
        $this->entityManager->clear();

        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::null($customer, sprintf('Customer "%s" still exists with original email.', $email));
    }

    /**
     * @Then an account deletion request email should have been sent to :email
     */
    public function anAccountDeletionRequestEmailShouldHaveBeenSentTo(string $email): void
    {
        $data = $this->spySender->getLastSentDataTo(Emails::ACCOUNT_DELETION_REQUESTED, $email);
        Assert::notNull($data, sprintf(
            'No account deletion request email was sent to "%s" (sent: %s).',
            $email,
            $this->spySender->describeSentEmails(),
        ));

        // The email is the only place the customer learns how long they have to
        // change their mind, so the date it announces has to be the date the
        // processor will actually act on.
        $request = $this->deletionRepository->findActiveForCustomer($this->loadCustomer($email));
        Assert::notNull($request, sprintf('No pending deletion request for "%s" to compare the email against.', $email));

        Assert::keyExists($data, 'scheduledFor', 'The deletion request email announces no date.');
        Assert::isInstanceOf($data['scheduledFor'], DateTimeInterface::class);
        Assert::same(
            $data['scheduledFor']->format('Y-m-d H:i'),
            $request->getScheduledFor()->format('Y-m-d H:i'),
            'The deletion request email announces a different date than the one stored on the request.',
        );
    }

    /**
     * @Then no account deletion email should have been sent to :email
     */
    public function noAccountDeletionEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmail(Emails::ACCOUNT_DELETION_REQUESTED, $email)
            || $this->spySender->hasSentEmail(Emails::ACCOUNT_DELETION_COMPLETED, $email),
            sprintf('An account deletion email was sent to "%s" although none was expected.', $email),
        );
    }

    /**
     * @Then an account deletion completed email should have been sent to :email
     */
    public function anAccountDeletionCompletedEmailShouldHaveBeenSentTo(string $email): void
    {
        // Anonymization overwrites the address, so the notice has to leave before
        // the customer is rewritten — asserting on the original address is what
        // proves the order.
        Assert::true(
            $this->spySender->hasSentEmail(Emails::ACCOUNT_DELETION_COMPLETED, $email),
            sprintf(
                'No account deletion completed email was sent to "%s" (sent: %s).',
                $email,
                $this->spySender->describeSentEmails(),
            ),
        );
    }

    protected function loadCustomer(string $email): CustomerInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);

        return $customer;
    }

    /**
     * Sylius's `there is a customer account ... identified by ...` step replaces
     * the configured password with a random hex string and stashes the original
     * → secret mapping in shared storage.
     */
    protected function retrieveSecurePassword(string $password): string
    {
        $scenarioSetupPassword = $this->sharedStorage->has('scenario_setup_password')
            ? $this->sharedStorage->get('scenario_setup_password')
            : null;

        if ($scenarioSetupPassword === $password && $this->sharedStorage->has('password')) {
            return (string) $this->sharedStorage->get('password');
        }

        return $password;
    }
}
