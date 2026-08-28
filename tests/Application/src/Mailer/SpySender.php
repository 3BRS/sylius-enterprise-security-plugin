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
     * @var array<int, array{code: string, recipients: array<string>, data: array<string, mixed>}>
     */
    protected static array $sentEmails = [];

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
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastSentData(string $code): array
    {
        for ($i = count(self::$sentEmails) - 1; $i >= 0; --$i) {
            if (self::$sentEmails[$i]['code'] === $code) {
                return self::$sentEmails[$i]['data'];
            }
        }

        return [];
    }

    /**
     * Deliberately takes the code as well: asking only whether *something*
     * reached an address passes even when the wrong email did, and the code is
     * what tells a login notification from a deletion notice.
     */
    public function hasSentEmail(string $code, string $recipient): bool
    {
        return $this->findLastSentEmail($code, $recipient) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastSentDataTo(string $code, string $recipient): ?array
    {
        return $this->findLastSentEmail($code, $recipient)['data'] ?? null;
    }

    /**
     * Renders what actually went out, so a failing expectation says which email
     * was sent instead of merely that the expected one was not.
     */
    public function describeSentEmails(): string
    {
        if (self::$sentEmails === []) {
            return 'no emails were sent';
        }

        return implode(', ', array_map(
            static fn (array $email): string => sprintf('%s -> %s', $email['code'], implode('/', $email['recipients'])),
            self::$sentEmails,
        ));
    }

    public function reset(): void
    {
        self::$sentEmails = [];
    }

    /**
     * @return array{code: string, recipients: array<string>, data: array<string, mixed>}|null
     */
    protected function findLastSentEmail(string $code, string $recipient): ?array
    {
        for ($i = count(self::$sentEmails) - 1; $i >= 0; --$i) {
            $email = self::$sentEmails[$i];
            if ($email['code'] === $code && in_array($recipient, $email['recipients'], true)) {
                return $email;
            }
        }

        return null;
    }
}
