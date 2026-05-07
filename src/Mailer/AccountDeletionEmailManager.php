<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

class AccountDeletionEmailManager implements AccountDeletionEmailManagerInterface
{
    public function __construct(
        protected SenderInterface $emailSender,
    ) {
    }

    public function sendDeletionRequested(CustomerInterface $customer, \DateTimeImmutable $scheduledFor): void
    {
        $email = $customer->getEmail();
        if ($email === null) {
            return;
        }

        $this->emailSender->send(
            code: Emails::ACCOUNT_DELETION_REQUESTED,
            recipients: [$email],
            data: [
                'customer' => $customer,
                'scheduledFor' => $scheduledFor,
            ],
        );
    }

    public function sendDeletionCompleted(CustomerInterface $customer): void
    {
        $email = $customer->getEmail();
        if ($email === null) {
            return;
        }

        $this->emailSender->send(
            code: Emails::ACCOUNT_DELETION_COMPLETED,
            recipients: [$email],
            data: [
                'customer' => $customer,
            ],
        );
    }
}
