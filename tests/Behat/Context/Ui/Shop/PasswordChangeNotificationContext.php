<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Component\User\Security\PasswordUpdaterInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use Webmozart\Assert\Assert;

class PasswordChangeNotificationContext implements Context
{
    public function __construct(
        private SpySender $spySender,
        private CustomerRepositoryInterface $customerRepository,
        private PasswordUpdaterInterface $passwordUpdater,
        private EntityManagerInterface $entityManager,
        private Session $session,
        private RouterInterface $router,
        private UserRepositoryInterface $shopUserRepository,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
    }

    /**
     * @Given the customer :email has the password :password set directly
     */
    public function theCustomerHasThePasswordSetDirectly(string $email, string $password): void
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::notNull($customer, sprintf('Customer with email "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user, sprintf('Customer "%s" has no user account.', $email));

        $user->setPlainPassword($password);
        $this->passwordUpdater->updatePassword($user);
        $this->entityManager->flush();
    }

    /**
     * @Given all password change notification emails are cleared
     */
    public function allPasswordChangeNotificationEmailsAreCleared(): void
    {
        $this->spySender->reset();
    }

    /**
     * @Then a password change notification email should have been sent to :email
     */
    public function aPasswordChangeNotificationEmailShouldHaveBeenSentTo(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmailToRecipient($email),
            sprintf('Expected a password change notification email to be sent to "%s", but it was not.', $email),
        );
    }

    /**
     * @Given a password reset token :token has been issued to :email
     */
    public function aPasswordResetTokenHasBeenIssuedTo(string $token, string $email): void
    {
        $user = $this->shopUserRepository->findOneByEmail($email);
        Assert::notNull($user, sprintf('Shop user "%s" not found.', $email));

        $user->setPasswordResetToken($token);
        $user->setPasswordRequestedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * @When I reset my password using token :token to :newPassword
     */
    public function iResetMyPasswordUsingTokenTo(string $token, string $newPassword): void
    {
        $this->session->visit($this->router->generate('sylius_shop_password_reset', ['_locale' => 'en_US', 'token' => $token]));

        $page = $this->session->getPage();
        $page->find('css', '[data-test-password-reset-new]')?->setValue($newPassword);
        $page->find('css', '[data-test-password-reset-confirmation]')?->setValue($newPassword);
        $page->pressButton('Reset');
    }
}
