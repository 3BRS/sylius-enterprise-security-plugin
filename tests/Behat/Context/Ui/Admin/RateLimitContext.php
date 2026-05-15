<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

class RateLimitContext implements Context
{
    /** @param UserRepositoryInterface<\Sylius\Component\User\Model\UserInterface> $adminUserRepository */
    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $adminUserRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @Given the admin login rate limit is set to :limit requests per minute
     *
     * Marker step — actual limits come from `three_brs_sylius_enterprise_security_plugin.yaml`
     * in the test app. The step exists so the scenario reads naturally.
     */
    public function adminLoginRateLimitIsSet(int $limit): void
    {
        Assert::greaterThan($limit, 0);
    }

    /**
     * @When I try to sign in to the admin panel with email :email and password :password
     */
    public function iTryToSignIn(string $email, string $password): void
    {
        $this->session->visit($this->router->generate('sylius_admin_login'));
        $page = $this->session->getPage();
        $page->fillField('_username', $email);
        $page->fillField('_password', $password);
        $page->pressButton('Login');
    }

    /**
     * @Then I should see the too-many-requests message
     */
    public function iShouldSeeTooManyRequestsMessage(): void
    {
        $statusCode = $this->session->getStatusCode();
        $content = (string) $this->session->getPage()->getContent();

        Assert::true(
            $statusCode === 429 || str_contains($content, 'Too many requests'),
            sprintf('Expected 429 status or rate-limit flash; got %d.', $statusCode),
        );
    }
}
