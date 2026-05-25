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
        protected GracePeriodCalculatorInterface $gracePeriodCalculator,
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
        $request->setScheduledFor($this->gracePeriodCalculator->calculateScheduledFor($now, $this->gracePeriodDays));

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
