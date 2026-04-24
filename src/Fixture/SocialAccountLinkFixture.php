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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLink;

class SocialAccountLinkFixture extends AbstractFixture implements SocialAccountLinkFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface>                              $customerRepository
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
        return 'three_brs_social_account_link';
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

            foreach ((array) $entry['links'] as $linkEntry) {
                $link = new CustomerSocialAccountLink();
                $link->setShopUser($shopUser);
                $link->setProvider((string) $linkEntry['provider']);
                $link->setProviderUserId((string) $linkEntry['provider_user_id']);
                $link->setEmail(isset($linkEntry['email']) ? (string) $linkEntry['email'] : $entry['email']);
                $this->entityManager->persist($link);
            }
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneByEmail(strtolower((string) $entry['email']));
            if (!$adminUser instanceof AdminUserInterface) {
                continue;
            }

            foreach ((array) $entry['links'] as $linkEntry) {
                $link = new AdminUserSocialAccountLink();
                $link->setAdminUser($adminUser);
                $link->setProvider((string) $linkEntry['provider']);
                $link->setProviderUserId((string) $linkEntry['provider_user_id']);
                $link->setEmail(isset($linkEntry['email']) ? (string) $linkEntry['email'] : $entry['email']);
                $this->entityManager->persist($link);
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
                            ->arrayNode('links')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('provider')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('provider_user_id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('email')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin_users')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('links')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('provider')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('provider_user_id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('email')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
