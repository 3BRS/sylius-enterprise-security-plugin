<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerDueDeletionsProcessorInterface;

#[AsCommand(
    name: 'three-brs:account-deletion:process-due',
    description: 'Anonymize customers whose account deletion grace period has elapsed.',
)]
class ProcessDueAccountDeletionsCommand extends Command implements ProcessDueAccountDeletionsCommandInterface
{
    public function __construct(
        protected CustomerDueDeletionsProcessorInterface $dueProcessor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processed = $this->dueProcessor->process();

        $output->writeln(sprintf('Processed %d due account deletion request(s).', $processed));

        return Command::SUCCESS;
    }
}
