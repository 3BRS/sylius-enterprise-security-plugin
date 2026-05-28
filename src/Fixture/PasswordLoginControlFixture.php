<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Model\UserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerLoginPreference;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Model\PasswordLoginControlAdminUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerLoginPreferenceRepositoryInterface;

class PasswordLoginControlFixture extends AbstractFixture implements PasswordLoginControlFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface>                      $customerRepository
     * @param UserRepositoryInterface<UserInterface> $adminUserRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected UserRepositoryInterface $adminUserRepository,
        protected CustomerLoginPreferenceRepositoryInterface $customerPreferenceRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_password_login_control';
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
            if (!$shopUser instanceof ShopUserInterface) {
                continue;
            }

            $preference = $this->customerPreferenceRepository->findOneByShopUser($shopUser);
            if ($preference === null) {
                $preference = new CustomerLoginPreference();
                $preference->setShopUser($shopUser);
                $this->entityManager->persist($preference);
            }
            $preference->setPasswordLoginAllowed((bool) $entry['password_login_allowed']);
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneByEmail(strtolower((string) $entry['email']));
            if (!$adminUser instanceof PasswordLoginControlAdminUserInterface) {
                continue;
            }

            // Admins store the flag on the user itself (PasswordLoginControlAdminUserTrait).
            $adminUser->setPasswordLoginAllowed((bool) $entry['password_login_allowed']);
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
                            ->booleanNode('password_login_allowed')->defaultTrue()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin_users')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->booleanNode('password_login_allowed')->defaultTrue()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
