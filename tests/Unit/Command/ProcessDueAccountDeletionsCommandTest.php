<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Command\ProcessDueAccountDeletionsCommand;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDeletionServiceInterface;

#[CoversClass(ProcessDueAccountDeletionsCommand::class)]
class ProcessDueAccountDeletionsCommandTest extends TestCase
{
    public function testReportsProcessedCount(): void
    {
        $service = $this->createStub(CustomerDeletionServiceInterface::class);
        $service->method('processDueRequests')->willReturn(3);

        $tester = new CommandTester(new ProcessDueAccountDeletionsCommand($service));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Processed 3 due account deletion request(s).', $tester->getDisplay());
    }
}
