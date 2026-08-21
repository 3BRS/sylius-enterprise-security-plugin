<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasswordHistory;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasswordHistory;

class PasswordHistoryFixture extends AbstractFixture implements PasswordHistoryFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<\Sylius\Component\Core\Model\CustomerInterface> $customerRepository
     * @param UserRepositoryInterface<\Sylius\Component\User\Model\UserInterface>         $adminUserRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected UserRepositoryInterface $adminUserRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_password_history';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['shop_users'] as $entry) {
            $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if ($customer === null) {
                continue;
            }

            $shopUser = $customer->getUser();
            if (!$shopUser instanceof ShopUserInterface) {
                continue;
            }

            foreach ($entry['passwords'] as $plainPassword) {
                $history = new CustomerPasswordHistory();
                $history->setShopUser($shopUser);
                $history->setPasswordHash(password_hash($plainPassword, \PASSWORD_BCRYPT));
                $this->entityManager->persist($history);
            }
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($entry['email'])]);
            if (!$adminUser instanceof AdminUserInterface) {
                continue;
            }

            foreach ($entry['passwords'] as $plainPassword) {
                $history = new AdminUserPasswordHistory();
                $history->setAdminUser($adminUser);
                $history->setPasswordHash(password_hash($plainPassword, \PASSWORD_BCRYPT));
                $this->entityManager->persist($history);
            }
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('shop_users')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('passwords')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin_users')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('passwords')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
