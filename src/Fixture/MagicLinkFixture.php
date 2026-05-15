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
use ThreeBRS\EnterpriseSecurityBundle\MagicLink\MagicLinkTokenGeneratorInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserMagicLinkToken;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerMagicLinkToken;

class MagicLinkFixture extends AbstractFixture implements MagicLinkFixtureInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface>                      $customerRepository
     * @param UserRepositoryInterface<\Sylius\Component\User\Model\UserInterface> $adminUserRepository
     */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected UserRepositoryInterface $adminUserRepository,
        protected EntityManagerInterface $entityManager,
        protected MagicLinkTokenGeneratorInterface $tokenGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_magic_link';
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

            foreach ((array) $entry['tokens'] as $tokenEntry) {
                $token = new CustomerMagicLinkToken();
                $token->setShopUser($shopUser);
                $token->setTokenHash($this->tokenGenerator->hash((string) $tokenEntry['plain_token']));
                $token->setExpiresAt(new \DateTimeImmutable((string) $tokenEntry['expires_at']));
                if ($tokenEntry['used_at'] !== null) {
                    $token->setUsedAt(new \DateTimeImmutable((string) $tokenEntry['used_at']));
                }
                $this->entityManager->persist($token);
            }
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$adminUser instanceof AdminUserInterface) {
                continue;
            }

            foreach ((array) $entry['tokens'] as $tokenEntry) {
                $token = new AdminUserMagicLinkToken();
                $token->setAdminUser($adminUser);
                $token->setTokenHash($this->tokenGenerator->hash((string) $tokenEntry['plain_token']));
                $token->setExpiresAt(new \DateTimeImmutable((string) $tokenEntry['expires_at']));
                if ($tokenEntry['used_at'] !== null) {
                    $token->setUsedAt(new \DateTimeImmutable((string) $tokenEntry['used_at']));
                }
                $this->entityManager->persist($token);
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
                            ->arrayNode('tokens')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('plain_token')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('expires_at')->defaultValue('+5 minutes')->end()
                                        ->scalarNode('used_at')->defaultNull()->end()
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
                            ->arrayNode('tokens')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('plain_token')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('expires_at')->defaultValue('+5 minutes')->end()
                                        ->scalarNode('used_at')->defaultNull()->end()
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
