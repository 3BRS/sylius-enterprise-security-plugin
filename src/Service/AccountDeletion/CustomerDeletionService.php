<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\AccountDeletion\GracePeriodCalculatorInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsProviderInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\Session\CustomerSessionTrackerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Settings\SecuritySettingsBounds;

class CustomerDeletionService implements CustomerDeletionServiceInterface
{
    public function __construct(
        protected CustomerDeletionRequestRepositoryInterface $repository,
        protected CustomerAnonymizerInterface $anonymizer,
        protected AccountDeletionEmailManagerInterface $emailManager,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected LoggerInterface $logger,
        protected GracePeriodCalculatorInterface $gracePeriodCalculator,
        protected CustomerSessionTrackerInterface $sessionTracker,
        protected SettingsProviderInterface $settings,
    ) {
    }

    public function requestDeletion(CustomerInterface $customer): CustomerDeletionRequestInterface
    {
        if ($this->repository->findActiveForCustomer($customer) !== null) {
            throw new \RuntimeException('Customer already has a pending deletion request.');
        }

        $now = $this->clock->now();

        $request = new CustomerDeletionRequest();
        $request->setCustomer($customer);
        $request->setScheduledFor($this->gracePeriodCalculator->calculateScheduledFor($now, $this->getGracePeriodDays()));

        $this->disableShopUser($customer);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        // Disabling the user stops the next sign-in, not the sessions already open:
        // nothing re-reads isEnabled() on a later request, and ShopUser does not
        // implement EquatableInterface, so ContextListener sees no change either. A
        // customer who asked to be erased from one device would otherwise keep
        // browsing on another for the whole grace period. The admin block path does
        // the same thing, for the same reason.
        //
        // Revoked after the flush above rather than inside disableShopUser(): the
        // tracker commits, and doing it earlier would persist the disabled account
        // before the request that justifies it.
        $this->revokeSessions($customer);

        // Email is best-effort: the request is already committed, so a transient SMTP
        // failure must not unwind the deletion request. Admin can still see the pending
        // request in the UI and resend manually if needed.
        try {
            $this->emailManager->sendDeletionRequested($customer, $request->getScheduledFor());
        } catch (\Throwable $exception) {
            $this->logger->warning('three_brs.account_deletion.requested_email_failed', [
                'customer_id' => $customer->getId(),
                'reason' => $exception->getMessage(),
            ]);
        }

        return $request;
    }

    public function cancelByAdmin(CustomerDeletionRequestInterface $request, AdminUserInterface $admin): void
    {
        if (!$request->isPending()) {
            throw new \RuntimeException('Cannot cancel a deletion request that is no longer pending.');
        }

        $request->setCancelledAt($this->clock->now());
        $request->setCancelledByAdmin($admin);

        $this->enableShopUser($request->getCustomer());

        $this->entityManager->flush();
    }

    /**
     * A no-op when session management is off, since no rows exist to revoke. The
     * open sessions of an installation that does not track them stay open — closing
     * those needs a check on the live `enabled` flag, which this plugin does not
     * make on every request.
     */
    protected function revokeSessions(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        if ($user instanceof ShopUserInterface) {
            $this->sessionTracker->revokeAll($user);
        }
    }

    protected function disableShopUser(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        if ($user instanceof ShopUserInterface) {
            $user->setEnabled(false);
        }
    }

    protected function enableShopUser(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        if ($user instanceof ShopUserInterface) {
            $user->setEnabled(true);
        }
    }

    /**
     * The grace period is what an administrator sets in Security Settings, not what
     * the container was built with — the field was editable, saved and read back into
     * the form, while requests kept being scheduled on the configuration file's value.
     *
     * The stamped date is what the customer is told and what the cron acts on, so a
     * value from outside the range the form accepts is clamped rather than trusted:
     * zero would erase the account the same day the request arrives.
     */
    protected function getGracePeriodDays(): int
    {
        $days = $this->settings->getInt('account_deletion.grace_period_days', SettingsScope::CUSTOMER);

        return max(1, min(SecuritySettingsBounds::ACCOUNT_DELETION_GRACE_PERIOD_DAYS_MAX, $days));
    }
}
