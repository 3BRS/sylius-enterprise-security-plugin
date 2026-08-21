<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationAdminUserInterface;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;

class PasswordExpirationFixture extends AbstractFixture implements PasswordExpirationFixtureInterface
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
        return 'three_brs_password_expiration';
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
            if (!$shopUser instanceof PasswordExpirationShopUserInterface) {
                continue;
            }

            $this->applyExpiration($shopUser, $entry);
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($entry['email'])]);
            if (!$adminUser instanceof PasswordExpirationAdminUserInterface) {
                continue;
            }

            $this->applyExpiration($adminUser, $entry);
        }

        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $entry */
    protected function applyExpiration(PasswordExpirationShopUserInterface|PasswordExpirationAdminUserInterface $user, array $entry): void
    {
        if ((bool) $entry['force_change']) {
            $user->setForcePasswordChange(true);

            return;
        }

        if ($entry['password_changed_days_ago'] !== null) {
            $user->setPasswordChangedAt(
                new \DateTimeImmutable(sprintf('-%d days', $entry['password_changed_days_ago'])),
            );
        }
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
                            ->booleanNode('force_change')->defaultFalse()->end()
                            ->integerNode('password_changed_days_ago')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin_users')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->booleanNode('force_change')->defaultFalse()->end()
                            ->integerNode('password_changed_days_ago')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
