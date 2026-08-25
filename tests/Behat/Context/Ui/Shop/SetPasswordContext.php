<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

class SetPasswordContext implements Context
{
    /** @param UserRepositoryInterface<ShopUserInterface> $shopUserRepository */
    public function __construct(
        protected Session $session,
        protected RouterInterface $router,
        protected ExampleFactoryInterface $shopUserExampleFactory,
        protected UserRepositoryInterface $shopUserRepository,
        protected EntityManagerInterface $entityManager,
        protected UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @Given there is a passwordless customer account :email
     */
    public function thereIsAPasswordlessCustomerAccount(string $email): void
    {
        /** @var ShopUserInterface $user */
        $user = $this->shopUserExampleFactory->create([
            'email' => $email,
            'password' => 'TempPassword1!',
            'enabled' => true,
        ]);
        $this->shopUserRepository->add($user);

        // Simulate an account created via OAuth / magic link / passkey: no password.
        $user->setPlainPassword(null);
        $user->setPassword(null);
        $this->entityManager->flush();
    }

    /**
     * @When I visit the set password page
     */
    public function iVisitTheSetPasswordPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_shop_set_password', ['_locale' => 'en_US'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @When I visit my account dashboard
     */
    public function iVisitMyAccountDashboard(): void
    {
        $this->session->visit($this->router->generate('sylius_shop_account_dashboard', ['_locale' => 'en_US'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * @When I set my new password to :password
     */
    public function iSetMyNewPasswordTo(string $password): void
    {
        $page = $this->session->getPage();
        $form = $page->find('css', '#three_brs_set_password_form');
        Assert::notNull($form, 'Set password form not found on the page.');

        $form->find('css', 'input[name="three_brs_set_password[newPassword][first]"]')?->setValue($password);
        $form->find('css', 'input[name="three_brs_set_password[newPassword][second]"]')?->setValue($password);
        $form->find('css', 'button[type="submit"]')?->click();
    }

    /**
     * @Then my password should be set successfully
     */
    public function myPasswordShouldBeSetSuccessfully(): void
    {
        Assert::true(
            $this->session->getPage()->hasContent('Your password has been set'),
            'Expected a success confirmation after setting the password.',
        );
    }

    /**
     * @Then the customer :email should be able to sign in with password :password
     */
    public function theCustomerShouldBeAbleToSignInWithPassword(string $email, string $password): void
    {
        $user = $this->findShopUser($email);
        $this->entityManager->refresh($user);

        Assert::notNull($user->getPassword(), sprintf('Customer "%s" still has no password.', $email));
        Assert::true(
            $this->passwordHasher->isPasswordValid($user, $password),
            sprintf('Customer "%s" cannot sign in with the new password.', $email),
        );
    }

    /**
     * @Then I should be offered to set a new password
     */
    public function iShouldBeOfferedToSetANewPassword(): void
    {
        Assert::true(
            $this->session->getPage()->hasContent('Set a new password'),
            'Expected the account to offer setting a new password.',
        );
    }

    protected function findShopUser(string $email): ShopUserInterface
    {
        $user = $this->shopUserRepository->findOneByEmail($email);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }
}
