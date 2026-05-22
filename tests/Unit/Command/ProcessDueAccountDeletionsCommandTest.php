<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Command\ProcessDueAccountDeletionsCommand;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDueDeletionsProcessorInterface;

#[CoversClass(ProcessDueAccountDeletionsCommand::class)]
class ProcessDueAccountDeletionsCommandTest extends TestCase
{
    public function testReportsProcessedCount(): void
    {
        $processor = $this->createStub(CustomerDueDeletionsProcessorInterface::class);
        $processor->method('process')->willReturn(3);

        $tester = new CommandTester(new ProcessDueAccountDeletionsCommand($processor));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Processed 3 due account deletion request(s).', $tester->getDisplay());
    }
}
