<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Component\User\Security\PasswordUpdaterInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\SpySender;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Mailer\Emails;
use Webmozart\Assert\Assert;

class PasswordChangeNotificationContext implements Context
{
    public function __construct(
        private SpySender $spySender,
        private UserRepositoryInterface $adminUserRepository,
        private PasswordUpdaterInterface $passwordUpdater,
        private EntityManagerInterface $entityManager,
        private Session $session,
        private RouterInterface $router,
    ) {
    }

    #[BeforeScenario]
    public function resetSentEmails(): void
    {
        $this->spySender->reset();
    }

    /**
     * @Given the administrator :email has the password :password set directly
     */
    public function theAdministratorHasThePasswordSetDirectly(string $email, string $password): void
    {
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));

        $adminUser->setPlainPassword($password);
        $this->passwordUpdater->updatePassword($adminUser);
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
     * @Then a password change notification email should have been sent to admin :email
     */
    public function aPasswordChangeNotificationEmailShouldHaveBeenSentToAdmin(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmail(Emails::PASSWORD_CHANGED, $email),
            sprintf('Expected a password change notification email to be sent to admin "%s", but it was not.', $email),
        );
    }

    /**
     * @Then no password change notification email should have been sent to admin :email
     */
    public function noPasswordChangeNotificationEmailShouldHaveBeenSentToAdmin(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmail(Emails::PASSWORD_CHANGED, $email),
            sprintf('Expected no password change notification email sent to admin "%s", but one was found.', $email),
        );
    }

    /**
     * @When I edit administrator :email
     */
    public function iEditAdministrator(string $email): void
    {
        $adminUser = $this->adminUserRepository->findOneByEmail($email);
        Assert::notNull($adminUser, sprintf('Administrator "%s" not found.', $email));

        $this->session->visit($this->router->generate('sylius_admin_admin_user_update', ['id' => $adminUser->getId()]));
    }

    /**
     * @When I change their password to :password
     */
    public function iChangeTheirPasswordTo(string $password): void
    {
        $this->session->getPage()->find('css', '#sylius_admin_admin_user_plainPassword')?->setValue($password);
    }

    /**
     * The template only prints the "somebody else did this" warning and the reset
     * link when `initiatedByUser` is false, so the flag decides whether the email
     * is a receipt or an alarm — and nothing else in the suite reads it.
     *
     * @Then the password change notification to admin :email should read as self-initiated
     */
    public function thePasswordChangeNotificationToAdminShouldReadAsSelfInitiated(string $email): void
    {
        Assert::false($this->somebodyElseChangedThePasswordOf($email), sprintf(
            'The password change notification to "%s" warns that somebody else made the change.',
            $email,
        ));
    }

    /**
     * @Then the password change notification to admin :email should warn that somebody else changed it
     */
    public function thePasswordChangeNotificationToAdminShouldWarnThatSomebodyElseChangedIt(string $email): void
    {
        Assert::true($this->somebodyElseChangedThePasswordOf($email), sprintf(
            'The password change notification to "%s" reads as self-initiated.',
            $email,
        ));
    }

    protected function somebodyElseChangedThePasswordOf(string $email): bool
    {
        $data = $this->spySender->getLastSentDataTo(Emails::PASSWORD_CHANGED, $email);
        Assert::notNull($data, sprintf(
            'No password change notification was sent to "%s" (sent: %s).',
            $email,
            $this->spySender->describeSentEmails(),
        ));
        Assert::keyExists($data, 'initiatedByUser', 'The password change notification does not say who made the change.');

        return $data['initiatedByUser'] === false;
    }
}
