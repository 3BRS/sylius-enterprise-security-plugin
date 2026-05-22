<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpBlacklistRepositoryInterface;

class IpBlacklistFixture extends AbstractFixture implements IpBlacklistFixtureInterface
{
    /** @param UserRepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserIpBlacklistRepositoryInterface $blacklistRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'three_brs_ip_blacklist';
    }

    /** @param array<mixed> $options */
    public function load(array $options): void
    {
        foreach ($options['admins'] as $entry) {
            $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower((string) $entry['email'])]);
            if (!$admin instanceof AdminUserInterface) {
                continue;
            }

            $blacklist = $this->blacklistRepository->findOneByAdminUser($admin);
            if ($blacklist === null) {
                $blacklist = new AdminUserIpBlacklist();
                $blacklist->setAdminUser($admin);
                $this->entityManager->persist($blacklist);
            }

            $blacklist->setEnabled((bool) $entry['enabled']);
            $cidrs = [];
            foreach ($entry['cidrs'] as $cidr) {
                if (is_string($cidr) && $cidr !== '') {
                    $cidrs[] = $cidr;
                }
            }
            $blacklist->setCidrs($cidrs);
            $blacklist->touchUpdatedAt();
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
