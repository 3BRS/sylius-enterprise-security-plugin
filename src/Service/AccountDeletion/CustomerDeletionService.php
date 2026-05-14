<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;

class CustomerDeletionService implements CustomerDeletionServiceInterface
{
    public function __construct(
        protected CustomerDeletionRequestRepositoryInterface $repository,
        protected CustomerAnonymizerInterface $anonymizer,
        protected AccountDeletionEmailManagerInterface $emailManager,
        protected EntityManagerInterface $entityManager,
        protected ClockInterface $clock,
        protected LoggerInterface $logger,
        protected int $gracePeriodDays,
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
        $request->setScheduledFor($now->add(new \DateInterval('P' . $this->gracePeriodDays . 'D')));

        $this->disableShopUser($customer);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

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

    public function processDueRequests(): int
    {
        $now = $this->clock->now();
        $due = $this->repository->findDue($now);
        $processed = 0;

        foreach ($due as $request) {
            $customer = $request->getCustomer();
            // Send the completion email BEFORE anonymizing — afterwards
            // customer.email points at deleted-{id}@anonymized.invalid.
            // Caught: SMTP failure must not block the deletion (otherwise the
            // customer's PII would linger because of an external system fault);
            // log + carry on with anonymisation.
            try {
                $this->emailManager->sendDeletionCompleted($customer);
            } catch (\Throwable $exception) {
                $this->logger->warning('three_brs.account_deletion.completed_email_failed', [
                    'customer_id' => $customer->getId(),
                    'reason' => $exception->getMessage(),
                ]);
            }
            $this->anonymizer->anonymize($customer);
            $request->setCompletedAt($now);
            $this->entityManager->flush();
            ++$processed;
        }

        return $processed;
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
}
