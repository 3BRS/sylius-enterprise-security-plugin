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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerRecoveryCode;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\TwoFactorAuthShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\RecoveryCodeGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Service\TotpSecretGeneratorInterface;

class TwoFactorFixture extends AbstractFixture implements TwoFactorFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<\Sylius\Component\Core\Model\CustomerInterface> $customerRepository
     * @param UserRepositoryInterface<\Sylius\Component\User\Model\UserInterface>         $adminUserRepository
     */
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private UserRepositoryInterface $adminUserRepository,
        private EntityManagerInterface $entityManager,
        private TotpSecretGeneratorInterface $totpGenerator,
        private RecoveryCodeGeneratorInterface $recoveryGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_two_factor';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['shop_users'] as $entry) {
            $customer = $this->customerRepository->findOneBy(['email' => $entry['email']]);
            if ($customer === null) {
                continue;
            }

            $shopUser = $customer->getUser();
            if (!$shopUser instanceof ShopUserInterface || !$shopUser instanceof TwoFactorAuthShopUserInterface) {
                continue;
            }

            $secret = $entry['secret'] !== null ? (string) $entry['secret'] : $this->totpGenerator->generateSecret();
            $shopUser->setTotpSecret($secret);
            $shopUser->setTwoFactorEnabled((bool) $entry['enabled']);

            $this->createCustomerRecoveryCodes($shopUser, (array) $entry['recovery_codes']);
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($entry['email'])]);
            if (!$adminUser instanceof AdminUserInterface || !$adminUser instanceof TwoFactorAuthAdminUserInterface) {
                continue;
            }

            $secret = $entry['secret'] !== null ? (string) $entry['secret'] : $this->totpGenerator->generateSecret();
            $adminUser->setTotpSecret($secret);
            $adminUser->setTwoFactorEnabled((bool) $entry['enabled']);

            $this->createAdminRecoveryCodes($adminUser, (array) $entry['recovery_codes']);
        }

        $this->entityManager->flush();
    }

    /** @param array<int, mixed> $codes */
    private function createCustomerRecoveryCodes(ShopUserInterface&TwoFactorAuthShopUserInterface $user, array $codes): void
    {
        foreach ($codes as $plain) {
            $record = new CustomerRecoveryCode();
            $record->setShopUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash((string) $plain));
            $this->entityManager->persist($record);
        }
    }

    /** @param array<int, mixed> $codes */
    private function createAdminRecoveryCodes(AdminUserInterface&TwoFactorAuthAdminUserInterface $user, array $codes): void
    {
        foreach ($codes as $plain) {
            $record = new AdminUserRecoveryCode();
            $record->setAdminUser($user);
            $record->setCodeHash($this->recoveryGenerator->hash((string) $plain));
            $this->entityManager->persist($record);
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
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->scalarNode('secret')->defaultNull()->end()
                            ->arrayNode('recovery_codes')
                                ->defaultValue([])
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
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->scalarNode('secret')->defaultNull()->end()
                            ->arrayNode('recovery_codes')
                                ->defaultValue([])
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
