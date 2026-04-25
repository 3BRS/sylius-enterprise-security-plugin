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
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredential;

class PasskeyFixture extends AbstractFixture implements PasskeyFixtureInterface
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
        return 'three_brs_passkey';
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

            foreach ((array) $entry['credentials'] as $credentialEntry) {
                $credential = new CustomerPasskeyCredential();
                $credential->setShopUser($shopUser);
                $credentialId = (string) $credentialEntry['credential_id'];
                $credential->setCredentialId($credentialId);
                $credential->setLabel((string) $credentialEntry['label']);
                $credential->setAaguid(Uuid::v4()->toRfc4122());
                $credential->setCredentialSource($this->buildPlaceholderSource($credentialId, (string) $shopUser->getId()));

                $this->entityManager->persist($credential);
            }
        }

        foreach ($options['admin_users'] as $entry) {
            $adminUser = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$adminUser instanceof AdminUserInterface) {
                continue;
            }

            foreach ((array) $entry['credentials'] as $credentialEntry) {
                $credential = new AdminUserPasskeyCredential();
                $credential->setAdminUser($adminUser);
                $credentialId = (string) $credentialEntry['credential_id'];
                $credential->setCredentialId($credentialId);
                $credential->setLabel((string) $credentialEntry['label']);
                $credential->setAaguid(Uuid::v4()->toRfc4122());
                $credential->setCredentialSource($this->buildPlaceholderSource($credentialId, (string) $adminUser->getId()));

                $this->entityManager->persist($credential);
            }
        }

        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    protected function buildPlaceholderSource(string $credentialId, string $userHandle): array
    {
        // Placeholder credential source — these are NOT real WebAuthn credentials and
        // cannot complete the cryptographic assertion ceremony. They exist so that the
        // list/remove UI flows can be exercised by Behat/manual testing.
        return [
            'publicKeyCredentialId' => base64_encode($credentialId),
            'type' => 'public-key',
            'transports' => ['internal'],
            'attestationType' => 'none',
            'trustPath' => ['type' => 'Webauthn\\TrustPath\\EmptyTrustPath'],
            'aaguid' => Uuid::v4()->toRfc4122(),
            'credentialPublicKey' => '',
            'userHandle' => base64_encode($userHandle),
            'counter' => 0,
        ];
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
                            ->arrayNode('credentials')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('credential_id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('label')->defaultValue('Passkey')->end()
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
                            ->arrayNode('credentials')
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('credential_id')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('label')->defaultValue('Passkey')->end()
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
