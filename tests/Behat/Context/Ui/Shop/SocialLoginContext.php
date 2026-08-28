<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\OAuth\FakeOAuthStateInterface;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerSocialAccountLink;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerSocialAccountLinkRepositoryInterface;
use Webmozart\Assert\Assert;

class SocialLoginContext implements Context
{
    public function __construct(
        private Session $session,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerSocialAccountLinkRepositoryInterface $linkRepository,
        private EntityManagerInterface $entityManager,
        private FakeOAuthStateInterface $fakeOAuthState,
        private SpySender $spySender,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
    }

    /**
     * @Given the :provider OAuth provider will return user :providerUserId with email :email
     */
    public function theOAuthProviderWillReturnUser(string $provider, string $providerUserId, string $email): void
    {
        $this->fakeOAuthState->seedUserInfo($provider, $providerUserId, $email, 'Social', 'User');
    }

    /**
     * @Given the customer :email is already linked to :provider with id :providerUserId
     */
    public function theCustomerIsAlreadyLinkedTo(string $email, string $provider, string $providerUserId): void
    {
        $user = $this->findShopUser($email);

        $link = new CustomerSocialAccountLink();
        $link->setShopUser($user);
        $link->setProvider($provider);
        $link->setProviderUserId($providerUserId);
        $link->setEmail($email);

        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    /**
     * @When I click the :provider social login button
     */
    public function iClickTheSocialLoginButton(string $provider): void
    {
        $this->session->visit('/en_US/login');
        $button = $this->session->getPage()->find('css', sprintf('[data-test-three-brs-social-login="%s"]', $provider));
        Assert::notNull($button, sprintf('Social login button for "%s" not found on page.', $provider));
        $button->click();
    }

    /**
     * @When I confirm the social link with the emailed code
     */
    public function iConfirmTheSocialLinkWithTheEmailedCode(): void
    {
        $this->submitConfirmCode($this->emailedLinkCode());
    }

    /**
     * @When I confirm the social link with an incorrect code
     */
    public function iConfirmTheSocialLinkWithAnIncorrectCode(): void
    {
        $real = $this->emailedLinkCode();
        $this->submitConfirmCode(str_pad((string) (((int) $real + 1) % 1000000), 6, '0', STR_PAD_LEFT));
    }

    /**
     * @Given the customer :email has no usable password
     */
    public function theCustomerHasNoUsablePassword(string $email): void
    {
        $user = $this->findShopUser($email);
        $user->setPassword(null);
        $this->entityManager->flush();
    }

    /**
     * @When I click the :provider link button on the social accounts page
     */
    public function iClickTheLinkButtonOnTheSocialAccountsPage(string $provider): void
    {
        $this->session->visit('/en_US/account/social-accounts');
        $button = $this->session->getPage()->find('css', sprintf('[data-test-three-brs-social-link="%s"]', $provider));
        Assert::notNull($button, sprintf('Social link button for "%s" not found on social accounts page.', $provider));
        $button->click();
    }

    /**
     * @When I unlink my :provider social account
     */
    public function iUnlinkMySocialAccount(string $provider): void
    {
        $this->session->visit('/en_US/account/social-accounts');
        $modalId = 'three-brs-shop-social-unlink-modal-' . $provider;
        $button = $this->session->getPage()->find('css', sprintf('[data-test-three-brs-modal-confirm="%s"]', $modalId));
        Assert::notNull($button, sprintf('Unlink confirm button for "%s" not found in modal.', $provider));
        $button->click();
    }

    /**
     * @Then the customer :email should not be linked to :provider
     */
    public function theCustomerShouldNotBeLinkedTo(string $email, string $provider): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);
        $link = $this->linkRepository->findOneByShopUserAndProvider($user, $provider);
        Assert::null($link, sprintf('Expected no "%s" link for "%s", but found one.', $provider, $email));
    }

    /**
     * @Then the customer :email should still be linked to :provider
     */
    public function theCustomerShouldStillBeLinkedTo(string $email, string $provider): void
    {
        $this->entityManager->clear();
        $user = $this->findShopUser($email);
        $link = $this->linkRepository->findOneByShopUserAndProvider($user, $provider);
        Assert::notNull($link, sprintf('Expected "%s" link for "%s" to still exist.', $provider, $email));
    }

    /**
     * @Then I should be on the social link confirm page
     */
    public function iShouldBeOnTheSocialLinkConfirmPage(): void
    {
        $url = $this->session->getCurrentUrl();
        Assert::contains($url, '/oauth/confirm-link', sprintf('Expected confirm-link URL, got "%s".', $url));
    }

    /**
     * @Then I should be logged in as :email
     */
    public function iShouldBeLoggedInAs(string $email): void
    {
        $url = $this->session->getCurrentUrl();

        if (str_contains($url, '/login') || str_contains($url, '/oauth/')) {
            $html = $this->session->getPage()->getHtml();
            $alerts = '';
            if (preg_match_all('#<div[^>]*(?:alert|flash)[^>]*>.*?</div>\s*</div>#s', $html, $matches)) {
                $alerts = implode(' ||| ', $matches[0]);
            }
            $alerts = preg_replace('/\s+/', ' ', $alerts);
            throw new \RuntimeException(sprintf(
                "URL='%s' ALERTS=%s",
                $url,
                substr((string) $alerts, 0, 2000),
            ));
        }

        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found after social login.', $email));
    }

    /**
     * @Then a social link should exist for :email with :provider and provider id :providerUserId
     */
    public function aSocialLinkShouldExistFor(string $email, string $provider, string $providerUserId): void
    {
        $this->entityManager->clear();
        $link = $this->linkRepository->findByProviderAndProviderUserId($provider, $providerUserId);
        Assert::notNull($link, sprintf('No %s link found for provider_user_id "%s".', $provider, $providerUserId));
        Assert::same($link->getEmail(), $email);
    }

    /**
     * @Then the customer :email should exist
     */
    public function theCustomerShouldExist(string $email): void
    {
        $this->entityManager->clear();
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" was not created.', $email));
    }

    /**
     * @Then I should see a social-login error
     */
    public function iShouldSeeASocialLoginError(): void
    {
        $page = $this->session->getPage();
        Assert::notNull(
            $page->find('css', '[data-test-three-brs-social-confirm-error]'),
            'Expected password-confirm error to be visible.',
        );
    }

    private function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }

    /**
     * The confirm-code scenarios below prove the code the form accepts, but not
     * that one ever leaves for the customer: they read it straight out of the spy.
     * This says an email really went out, and in the shape the form expects.
     *
     * @Then an account linking code email should have been sent to :email
     */
    public function anAccountLinkingCodeEmailShouldHaveBeenSentTo(string $email): void
    {
        $data = $this->spySender->getLastSentDataTo(Emails::OAUTH_LINK_CODE, $email);
        Assert::notNull($data, sprintf(
            'No account linking code was emailed to "%s" (sent: %s).',
            $email,
            $this->spySender->describeSentEmails(),
        ));
        Assert::keyExists($data, 'code', 'The account linking email carries no code.');
        Assert::regex((string) $data['code'], '/^\\d{6}$/', 'The emailed account linking code is not a six-digit code.');
    }

    private function emailedLinkCode(): string
    {
        $data = $this->spySender->getLastSentData(Emails::OAUTH_LINK_CODE);
        Assert::keyExists($data, 'code', 'No account-linking code was emailed.');

        return (string) $data['code'];
    }

    private function submitConfirmCode(string $code): void
    {
        $page = $this->session->getPage();
        $input = $page->find('css', '[data-test-three-brs-social-confirm-code]');
        Assert::notNull($input, 'Code confirm input not found on confirm-link page.');
        $input->setValue($code);

        $button = $page->find('css', '[data-test-three-brs-social-confirm-submit]');
        Assert::notNull($button, 'Confirm submit button not found.');
        $button->click();
    }
}
