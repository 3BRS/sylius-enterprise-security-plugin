<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer;

use Sylius\Component\Mailer\Sender\SenderInterface;

class SpySender implements SenderInterface
{
    /**
     * Static so state is shared across kernel reboots during Behat UI scenarios:
     * the context-held instance and the instance resolved inside an HTTP request
     * would otherwise diverge, and emails sent during the request would not be
     * visible to the assertion that runs afterwards in the context.
     *
     * @var array<int, array{code: string, recipients: array<string>}>
     */
    private static array $sentEmails = [];

    public function send(
        string $code,
        array $recipients,
        array $data = [],
        array $attachments = [],
        array $replyTo = [],
    ): void {
        self::$sentEmails[] = [
            'code' => $code,
            'recipients' => $recipients,
        ];
    }

    public function hasSentEmailToRecipient(string $recipient): bool
    {
        foreach (self::$sentEmails as $email) {
            if (in_array($recipient, $email['recipients'], true)) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
        self::$sentEmails = [];
    }
}
