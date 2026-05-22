<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use ThreeBRS\EnterpriseSecurityBundle\AccountDeletion\AbstractDueDeletionsProcessor;
use ThreeBRS\EnterpriseSecurityBundle\AccountDeletion\CustomerDeletionRequestRecordInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequestInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\AccountDeletionEmailManagerInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerDeletionRequestRepositoryInterface;

class CustomerDueDeletionsProcessor extends AbstractDueDeletionsProcessor implements CustomerDueDeletionsProcessorInterface
{
    public function __construct(
        CustomerDeletionRequestRepositoryInterface $repository,
        ClockInterface $clock,
        protected CustomerAnonymizerInterface $anonymizer,
        protected AccountDeletionEmailManagerInterface $emailManager,
        protected EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    ) {
        parent::__construct($repository, $clock, $logger);
    }

    protected function onBeforeAnonymize(CustomerDeletionRequestRecordInterface $request): void
    {
        \assert($request instanceof CustomerDeletionRequestInterface);

        $this->emailManager->sendDeletionCompleted($request->getCustomer());
    }

    protected function anonymize(CustomerDeletionRequestRecordInterface $request): void
    {
        \assert($request instanceof CustomerDeletionRequestInterface);

        $this->anonymizer->anonymize($request->getCustomer());
    }

    protected function commit(): void
    {
        $this->entityManager->flush();
    }
}
