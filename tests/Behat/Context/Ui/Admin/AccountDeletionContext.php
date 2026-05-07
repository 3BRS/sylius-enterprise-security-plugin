<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use Webmozart\Assert\Assert;

class AccountDeletionContext implements Context
{
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerDeletionRequestRepositoryInterface $deletionRepository,
        protected EntityManagerInterface $entityManager,
    ) {
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
     * @When I open the account deletions admin page
     */
    public function iOpenTheAccountDeletionsAdminPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_account_deletions', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @Then I should see :email in the pending deletions list
     */
    public function iShouldSeeInThePendingDeletionsList(string $email): void
    {
        Assert::true(
            $this->session->getPage()->hasContent($email),
            sprintf('Customer "%s" not visible in pending deletions list.', $email),
        );
    }

    /**
     * @When I cancel the deletion request for :email
     */
    public function iCancelTheDeletionRequestFor(string $email): void
    {
        $customer = $this->loadCustomer($email);
        $request = $this->deletionRepository->findActiveForCustomer($customer);
        Assert::notNull($request);

        $page = $this->session->getPage();
        $form = $page->find('css', sprintf('form[action$="/admin/account-deletions/%d/cancel"]', $request->getId()));
        Assert::notNull($form, sprintf('Cancel form not found for "%s".', $email));
        $form->find('css', 'button[type="submit"]')?->click();
    }

    /**
     * @Then no deletion request should be pending for :email
     */
    public function noDeletionRequestShouldBePendingFor(string $email): void
    {
        $this->entityManager->clear();
        $customer = $this->loadCustomer($email);

        Assert::null(
            $this->deletionRepository->findActiveForCustomer($customer),
            sprintf('A deletion request is still pending for "%s".', $email),
        );
    }

    /**
     * @Then the customer :email should be enabled
     */
    public function theCustomerShouldBeEnabled(string $email): void
    {
        $this->entityManager->clear();
        $customer = $this->loadCustomer($email);
        $shopUser = $customer->getUser();
        Assert::isInstanceOf($shopUser, ShopUserInterface::class);

        Assert::true($shopUser->isEnabled(), sprintf('Customer "%s" is still disabled.', $email));
    }

    protected function loadCustomer(string $email): CustomerInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($customer, CustomerInterface::class);

        return $customer;
    }
}
