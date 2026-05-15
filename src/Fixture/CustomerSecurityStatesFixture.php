<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\EnterpriseSecurityBundle\PasswordExpiration\PasswordExpirationShopUserInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSession;

class CustomerSecurityStatesFixture extends AbstractFixture implements CustomerSecurityStatesFixtureInterface
{
    /** @param CustomerRepositoryInterface<CustomerInterface> $customerRepository */
    public function __construct(
        protected CustomerRepositoryInterface $customerRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_customer_security_states';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['blocked'] as $entry) {
            $user = $this->loadShopUser((string) $entry['email']);
            if ($user === null) {
                continue;
            }
            $user->setEnabled(false);
        }

        foreach ($options['force_password_change'] as $entry) {
            $user = $this->loadShopUser((string) $entry['email']);
            if (!$user instanceof PasswordExpirationShopUserInterface) {
                continue;
            }
            $user->setForcePasswordChange(true);
        }

        foreach ($options['sessions'] as $entry) {
            $user = $this->loadShopUser((string) $entry['email']);
            if ($user === null) {
                continue;
            }

            foreach ($entry['active'] as $idx => $session) {
                $this->persistSession($user, sprintf('fixture-active-%d-%d', $user->getId() ?? 0, $idx), $session, revoked: false);
            }

            foreach ($entry['past'] as $idx => $session) {
                $this->persistSession($user, sprintf('fixture-past-%d-%d', $user->getId() ?? 0, $idx), $session, revoked: true);
            }
        }

        $this->entityManager->flush();
    }

    protected function loadShopUser(string $email): ?ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        if (!$customer instanceof CustomerInterface) {
            return null;
        }

        $user = $customer->getUser();

        return $user instanceof ShopUserInterface ? $user : null;
    }

    /**
     * @param array{user_agent?: string|null, ip?: string|null, country?: string|null, city?: string|null} $data
     */
    protected function persistSession(ShopUserInterface $user, string $sessionId, array $data, bool $revoked): void
    {
        $session = new CustomerSession();
        $session->setShopUser($user);
        $session->setSessionId($sessionId);
        $session->setUserAgent($data['user_agent'] ?? null);
        $session->setIpAddress($data['ip'] ?? null);
        $session->setCountry($data['country'] ?? null);
        $session->setCity($data['city'] ?? null);

        if ($revoked) {
            $session->setRevokedAt(new \DateTimeImmutable('-1 day'));
        }

        $this->entityManager->persist($session);
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('blocked')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('force_password_change')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('sessions')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('active')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('user_agent')->defaultNull()->end()
                                        ->scalarNode('ip')->defaultNull()->end()
                                        ->scalarNode('country')->defaultNull()->end()
                                        ->scalarNode('city')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('past')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('user_agent')->defaultNull()->end()
                                        ->scalarNode('ip')->defaultNull()->end()
                                        ->scalarNode('country')->defaultNull()->end()
                                        ->scalarNode('city')->defaultNull()->end()
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
