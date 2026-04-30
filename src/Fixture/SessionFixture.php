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
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserKnownDevice;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserSession;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerKnownDevice;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;

class SessionFixture extends AbstractFixture implements SessionFixtureInterface
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
        return 'three_brs_session';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['shop_users'] as $entry) {
            $customer = $this->customerRepository->findOneBy(['email' => $entry['email']]);
            if (!$customer instanceof CustomerInterface) {
                continue;
            }

            $shopUser = $customer->getUser();
            if (!$shopUser instanceof ShopUserInterface) {
                continue;
            }

            foreach ($entry['sessions'] as $sessionEntry) {
                $session = new CustomerSession();
                $session->setShopUser($shopUser);
                $session->setSessionId((string) $sessionEntry['session_id']);
                $session->setUserAgent($sessionEntry['user_agent'] === null ? null : (string) $sessionEntry['user_agent']);
                $session->setIpAddress($sessionEntry['ip_address'] === null ? null : (string) $sessionEntry['ip_address']);
                $session->setCountry($sessionEntry['country'] === null ? null : (string) $sessionEntry['country']);
                $session->setCity($sessionEntry['city'] === null ? null : (string) $sessionEntry['city']);
                $this->entityManager->persist($session);
            }

            foreach ($entry['known_devices'] as $fingerprint) {
                $device = new CustomerKnownDevice();
                $device->setShopUser($shopUser);
                $device->setFingerprint((string) $fingerprint);
                $this->entityManager->persist($device);
            }
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$adminUser instanceof AdminUserInterface) {
                continue;
            }

            foreach ($entry['sessions'] as $sessionEntry) {
                $session = new AdminUserSession();
                $session->setAdminUser($adminUser);
                $session->setSessionId((string) $sessionEntry['session_id']);
                $session->setUserAgent($sessionEntry['user_agent'] === null ? null : (string) $sessionEntry['user_agent']);
                $session->setIpAddress($sessionEntry['ip_address'] === null ? null : (string) $sessionEntry['ip_address']);
                $session->setCountry($sessionEntry['country'] === null ? null : (string) $sessionEntry['country']);
                $session->setCity($sessionEntry['city'] === null ? null : (string) $sessionEntry['city']);
                $this->entityManager->persist($session);
            }

            foreach ($entry['known_devices'] as $fingerprint) {
                $device = new AdminUserKnownDevice();
                $device->setAdminUser($adminUser);
                $device->setFingerprint((string) $fingerprint);
                $this->entityManager->persist($device);
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
                            ->arrayNode('sessions')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('session_id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('user_agent')->defaultNull()->end()
                                        ->scalarNode('ip_address')->defaultNull()->end()
                                        ->scalarNode('country')->defaultNull()->end()
                                        ->scalarNode('city')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('known_devices')
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
                            ->arrayNode('sessions')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('session_id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('user_agent')->defaultNull()->end()
                                        ->scalarNode('ip_address')->defaultNull()->end()
                                        ->scalarNode('country')->defaultNull()->end()
                                        ->scalarNode('city')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('known_devices')
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
