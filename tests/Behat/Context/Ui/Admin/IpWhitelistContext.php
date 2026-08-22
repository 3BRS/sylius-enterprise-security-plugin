<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserIpWhitelist;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserIpWhitelistRepositoryInterface;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsScope;
use ThreeBRS\EnterpriseSecurityBundle\Settings\SettingsWriterInterface;
use Webmozart\Assert\Assert;

class IpWhitelistContext implements Context
{
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected RepositoryInterface $adminUserRepository,
        protected AdminUserIpWhitelistRepositoryInterface $whitelistRepository,
        protected SettingsWriterInterface $settingsWriter,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the admin IP whitelist is enabled with global CIDRs :cidrs
     */
    public function adminIpWhitelistIsEnabledWithGlobalCidrs(string $cidrs): void
    {
        $list = $this->splitCidrs($cidrs);
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_whitelist.enabled' => true,
            'ip_whitelist.global_cidrs' => $list,
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given the admin IP whitelist is enabled with no global CIDRs
     */
    public function adminIpWhitelistIsEnabledWithNoGlobalCidrs(): void
    {
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_whitelist.enabled' => true,
            'ip_whitelist.global_cidrs' => [],
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given the admin IP whitelist is disabled
     */
    public function adminIpWhitelistIsDisabled(): void
    {
        $this->settingsWriter->setMany(SettingsScope::ADMIN, [
            'ip_whitelist.enabled' => false,
            'ip_whitelist.global_cidrs' => [],
        ]);
        $this->settingsWriter->flush();
    }

    /**
     * @Given administrator :email has per-admin IP whitelist enabled with CIDRs :cidrs
     */
    public function adminHasPerAdminWhitelistEnabled(string $email, string $cidrs): void
    {
        $this->upsertPerAdmin($email, true, $this->splitCidrs($cidrs));
    }

    /**
     * @Given administrator :email has per-admin IP whitelist disabled with CIDRs :cidrs
     */
    public function adminHasPerAdminWhitelistDisabled(string $email, string $cidrs): void
    {
        $this->upsertPerAdmin($email, false, $this->splitCidrs($cidrs));
    }

    /**
     * @When I open the IP whitelist admins page
     */
    public function iOpenTheIpWhitelistAdminsPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_ip_whitelist_admins', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @When I open the IP whitelist edit page for :email
     */
    public function iOpenTheIpWhitelistEditPageFor(string $email): void
    {
        $admin = $this->loadAdmin($email);
        $this->session->visit($this->router->generate(
            'three_brs_admin_ip_whitelist_admin_edit',
            ['id' => $admin->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    /**
     * The sign-in POST is answered by the firewall itself, with no controller behind
     * it, so it is only covered while the IP check runs above the firewall. Every
     * other scenario here reaches the panel through SecurityService::logIn(), which
     * puts the token into the session directly and never travels this route.
     *
     * Below the firewall this same request is handled by the authenticator, which
     * answers a redirect — not a 403 — whether the credentials pass or the CSRF token
     * is missing, so the status alone tells the two arrangements apart.
     *
     * @When I submit the admin sign-in form as :email with :password
     */
    public function iSubmitTheAdminSignInForm(string $email, string $password): void
    {
        $driver = $this->session->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class);

        $client = $driver->getClient();
        // The answer to this request is the assertion, so the redirect a successful
        // sign-in would return must not be followed — the page behind it is refused
        // as well, and following it would report that refusal instead.
        $client->followRedirects(false);

        $client->request(
            'POST',
            $this->router->generate('sylius_admin_login_check', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ['_username' => $email, '_password' => $password],
        );
    }

    /**
     * @When I open any admin page
     */
    public function iOpenAnyAdminPage(): void
    {
        $this->session->visit($this->router->generate('sylius_admin_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @Then the admin response status should be :code
     */
    public function adminResponseStatusShouldBe(int $code): void
    {
        Assert::same($this->session->getStatusCode(), $code);
    }

    /**
     * @Then I should see the IP whitelist admins list
     */
    public function iShouldSeeTheIpWhitelistAdminsList(): void
    {
        Assert::true(
            $this->session->getPage()->has('css', '[data-test-three-brs-ip-whitelist-admins]'),
            'IP whitelist admins list is not rendered.',
        );
    }

    /**
     * @Then administrator :email should have the IP whitelist :state
     */
    public function administratorShouldHaveTheIpWhitelist(string $email, string $state): void
    {
        $admin = $this->loadAdmin($email);
        $whitelist = $this->whitelistRepository->findOneByAdminUser($admin);

        if ($state === 'enabled') {
            Assert::notNull($whitelist);
            Assert::true($whitelist->isEnabled());

            return;
        }

        if ($state === 'disabled') {
            Assert::true($whitelist === null || !$whitelist->isEnabled());

            return;
        }

        throw new \InvalidArgumentException(sprintf('Unknown whitelist state "%s".', $state));
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

        $whitelist = $this->whitelistRepository->findOneByAdminUser($admin);
        if ($whitelist === null) {
            $whitelist = new AdminUserIpWhitelist();
            $whitelist->setAdminUser($admin);
            $this->entityManager->persist($whitelist);
        }

        $whitelist->setEnabled($enabled);
        $whitelist->setCidrs($cidrs);
        $whitelist->touchUpdatedAt();

        $this->entityManager->flush();
    }

    protected function loadAdmin(string $email): AdminUserInterface
    {
        $admin = $this->adminUserRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::isInstanceOf($admin, AdminUserInterface::class);

        return $admin;
    }
}
