<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpBlacklist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpBlacklistRepositoryInterface;
use Webmozart\Assert\Assert;

class IpBlacklistContext implements Context
{
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected RepositoryInterface $adminUserRepository,
        protected AdminUserIpBlacklistRepositoryInterface $blacklistRepository,
        protected SettingsWriterInterface $settingsWriter,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the admin IP blacklist is enabled with global CIDRs :cidrs
     */
    public function adminIpBlacklistIsEnabledWithGlobalCidrs(string $cidrs): void
    {
        $list = $this->splitCidrs($cidrs);
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_blacklist.enabled' => true,
            'ip_blacklist.global_cidrs' => $list,
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given the admin IP blacklist is enabled with no global CIDRs
     */
    public function adminIpBlacklistIsEnabledWithNoGlobalCidrs(): void
    {
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_blacklist.enabled' => true,
            'ip_blacklist.global_cidrs' => [],
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given the admin IP blacklist is disabled
     */
    public function adminIpBlacklistIsDisabled(): void
    {
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_blacklist.enabled' => false,
            'ip_blacklist.global_cidrs' => [],
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given administrator :email has per-admin IP blacklist enabled with CIDRs :cidrs
     */
    public function adminHasPerAdminBlacklistEnabled(string $email, string $cidrs): void
    {
        $this->upsertPerAdmin($email, true, $this->splitCidrs($cidrs));
    }

    /**
     * @Given administrator :email has per-admin IP blacklist disabled with CIDRs :cidrs
     */
    public function adminHasPerAdminBlacklistDisabled(string $email, string $cidrs): void
    {
        $this->upsertPerAdmin($email, false, $this->splitCidrs($cidrs));
    }

    /**
     * @When I open the IP blacklist admins page
     */
    public function iOpenTheIpBlacklistAdminsPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_ip_blacklist_admins', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @When I open the IP blacklist edit page for :email
     */
    public function iOpenTheIpBlacklistEditPageFor(string $email): void
    {
        $admin = $this->loadAdmin($email);
        $this->session->visit($this->router->generate(
            'three_brs_admin_ip_blacklist_admin_edit',
            ['id' => $admin->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    /**
     * @Then I should see the IP blacklist admins list
     */
    public function iShouldSeeTheIpBlacklistAdminsList(): void
    {
        Assert::true(
            $this->session->getPage()->has('css', '[data-test-three-brs-ip-blacklist-admins]'),
            'IP blacklist admins list is not rendered.',
        );
    }

    /**
     * @Then administrator :email should have the IP blacklist :state
     */
    public function administratorShouldHaveTheIpBlacklist(string $email, string $state): void
    {
        $admin = $this->loadAdmin($email);
        $blacklist = $this->blacklistRepository->findOneByAdminUser($admin);

        if ($state === 'enabled') {
            Assert::notNull($blacklist);
            Assert::true($blacklist->isEnabled());

            return;
        }

        if ($state === 'disabled') {
            Assert::true($blacklist === null || !$blacklist->isEnabled());

            return;
        }

        throw new \InvalidArgumentException(sprintf('Unknown blacklist state "%s".', $state));
    }

    /**
     * @return list<string>
     */
    protected function splitCidrs(string $cidrs): array
    {
        $list = [];
        foreach (preg_split('/\s*,\s*/', $cidrs) ?: [] as $cidr) {
            $cidr = trim($cidr);
            if ($cidr !== '') {
                $list[] = $cidr;
            }
        }

        return $list;
    }

    /**
     * @param list<string> $cidrs
     */
    protected function upsertPerAdmin(string $email, bool $enabled, array $cidrs): void
    {
        $admin = $this->loadAdmin($email);

        $blacklist = $this->blacklistRepository->findOneByAdminUser($admin);
        if ($blacklist === null) {
            $blacklist = new AdminUserIpBlacklist();
            $blacklist->setAdminUser($admin);
            $this->entityManager->persist($blacklist);
        }

        $blacklist->setEnabled($enabled);
        $blacklist->setCidrs($cidrs);
        $blacklist->touchUpdatedAt();

        $this->entityManager->flush();
    }

    protected function loadAdmin(string $email): AdminUserInterface
    {
        $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($admin, AdminUserInterface::class);

        return $admin;
    }
}
