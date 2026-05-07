<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerDeletionRequest;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\AccountDeletion\CustomerAnonymizerInterface;

class AccountDeletionFixture extends AbstractFixture implements AccountDeletionFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerAnonymizerInterface $anonymizer,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_account_deletion';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['pending_requests'] as $entry) {
            $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$customer instanceof CustomerInterface) {
                continue;
            }

            $now = new \DateTimeImmutable();

            $request = new CustomerDeletionRequest();
            $request->setCustomer($customer);
            $request->setScheduledFor($now->add(new \DateInterval('P' . (int) $entry['scheduled_in_days'] . 'D')));

            $shopUser = $customer->getUser();
            if ($shopUser instanceof ShopUserInterface) {
                $shopUser->setEnabled(false);
            }

            $this->entityManager->persist($request);
        }

        foreach ($options['anonymized'] as $entry) {
            $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$customer instanceof CustomerInterface) {
                continue;
            }

            $now = new \DateTimeImmutable();

            $request = new CustomerDeletionRequest();
            $request->setCustomer($customer);
            $request->setScheduledFor($now->sub(new \DateInterval('P1D')));
            $request->setCompletedAt($now);

            $this->entityManager->persist($request);
            $this->entityManager->flush();

            $this->anonymizer->anonymize($customer);
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('pending_requests')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('scheduled_in_days')->defaultValue(7)->min(1)->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('anonymized')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
