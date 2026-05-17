<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\Lockout\LockableShopUserInterface;

class LockoutFixture extends AbstractFixture implements LockoutFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface>                      $customerRepository
     * @param UserRepositoryInterface<\Sylius\Component\User\Model\UserInterface> $adminUserRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected UserRepositoryInterface $adminUserRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_lockout';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['shop_users'] as $entry) {
            $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$customer instanceof CustomerInterface) {
                continue;
            }

            $shopUser = $customer->getUser();
            if (!$shopUser instanceof ShopUserInterface || !$shopUser instanceof LockableShopUserInterface) {
                continue;
            }

            $shopUser->setFailedLoginAttempts((int) $entry['failed_attempts']);
            $shopUser->setLastFailedLoginAt(new \DateTimeImmutable());
            if ($entry['locked'] === true) {
                $now = new \DateTimeImmutable();
                $shopUser->setLockedAt($now);
                $shopUser->setLockoutUntil(
                    $entry['auto_unlock_in_seconds'] === null
                        ? null
                        : $now->modify(sprintf('+%d seconds', (int) $entry['auto_unlock_in_seconds'])),
                );
            }
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$adminUser instanceof AdminUserInterface || !$adminUser instanceof LockableAdminUserInterface) {
                continue;
            }

            $adminUser->setFailedLoginAttempts((int) $entry['failed_attempts']);
            $adminUser->setLastFailedLoginAt(new \DateTimeImmutable());
            if ($entry['locked'] === true) {
                $now = new \DateTimeImmutable();
                $adminUser->setLockedAt($now);
                $adminUser->setLockoutUntil(
                    $entry['auto_unlock_in_seconds'] === null
                        ? null
                        : $now->modify(sprintf('+%d seconds', (int) $entry['auto_unlock_in_seconds'])),
                );
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
                            ->integerNode('failed_attempts')->defaultValue(0)->min(0)->end()
                            ->booleanNode('locked')->defaultFalse()->end()
                            ->integerNode('auto_unlock_in_seconds')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin_users')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('failed_attempts')->defaultValue(0)->min(0)->end()
                            ->booleanNode('locked')->defaultFalse()->end()
                            ->integerNode('auto_unlock_in_seconds')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
