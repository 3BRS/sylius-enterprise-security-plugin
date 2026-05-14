<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;

class IpWhitelistFixture extends AbstractFixture implements IpWhitelistFixtureInterface
{
    public function __construct(
        protected RepositoryInterface $adminUserRepository,
        protected AdminUserIpWhitelistRepositoryInterface $whitelistRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_ip_whitelist';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['admins'] as $entry) {
            $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$admin instanceof AdminUserInterface) {
                continue;
            }

            $whitelist = $this->whitelistRepository->findOneByAdminUser($admin);
            if ($whitelist === null) {
                $whitelist = new AdminUserIpWhitelist();
                $whitelist->setAdminUser($admin);
                $this->entityManager->persist($whitelist);
            }

            $whitelist->setEnabled((bool) $entry['enabled']);
            $cidrs = [];
            foreach ($entry['cidrs'] as $cidr) {
                if (is_string($cidr) && $cidr !== '') {
                    $cidrs[] = $cidr;
                }
            }
            $whitelist->setCidrs($cidrs);
            $whitelist->touchUpdatedAt();
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('admins')
                    ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->isRequired()->cannotBeEmpty()->end()
                            ->booleanNode('enabled')->defaultTrue()->end()
                            ->arrayNode('cidrs')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
