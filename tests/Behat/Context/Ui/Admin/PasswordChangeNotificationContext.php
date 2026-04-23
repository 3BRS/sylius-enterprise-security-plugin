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
     * @When the administrator :email password is changed to :password directly
     */
    public function theAdministratorPasswordIsChangedDirectly(string $email, string $password): void
    {
        $this->theAdministratorHasThePasswordSetDirectly($email, $password);
    }

    /**
     * @Then a password change notification email should have been sent to admin :email
     */
    public function aPasswordChangeNotificationEmailShouldHaveBeenSentToAdmin(string $email): void
    {
        Assert::true(
            $this->spySender->hasSentEmailToRecipient($email),
            sprintf('Expected a password change notification email to be sent to admin "%s", but it was not.', $email),
        );
    }

    /**
     * @Then no password change notification email should have been sent to admin :email
     */
    public function noPasswordChangeNotificationEmailShouldHaveBeenSentToAdmin(string $email): void
    {
        Assert::false(
            $this->spySender->hasSentEmailToRecipient($email),
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
     * @When I save my changes
     */
    public function iSaveMyChanges(): void
    {
        $this->session->getPage()->find('css', '[data-test-update-changes-button]')?->click();
    }
}
