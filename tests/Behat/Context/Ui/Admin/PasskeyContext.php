<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\AdminUserPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\AdminUserPasskeyCredentialRepositoryInterface;
use Webmozart\Assert\Assert;

class PasskeyContext implements Context
{
    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $adminUserRepository,
        protected AdminUserPasskeyCredentialRepositoryInterface $credentialRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @When I visit the admin passkey management page
     */
    public function iVisitTheAdminPasskeyManagementPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_admin_passkey_index'));
    }

    /**
     * @Given an admin passkey :credentialId labelled :label exists for :email
     */
    public function anAdminPasskeyExistsFor(string $credentialId, string $label, string $email): void
    {
        $adminUser = $this->findAdminUser($email);

        $credential = new AdminUserPasskeyCredential();
        $credential->setAdminUser($adminUser);
        $credential->setCredentialId($credentialId);
        $credential->setLabel($label);
        $credential->setAaguid(Uuid::v4()->toRfc4122());
        $credential->setCredentialSource([
            'publicKeyCredentialId' => base64_encode($credentialId),
            'type' => 'public-key',
            'transports' => ['usb'],
            'attestationType' => 'none',
            'trustPath' => ['type' => 'Webauthn\\TrustPath\\EmptyTrustPath'],
            'aaguid' => $credential->getAaguid(),
            'credentialPublicKey' => '',
            'userHandle' => base64_encode((string) $adminUser->getId()),
            'counter' => 0,
        ]);

        $this->entityManager->persist($credential);
        $this->entityManager->flush();
    }

    /**
     * @Then I should see no registered admin passkeys
     */
    public function iShouldSeeNoRegisteredAdminPasskeys(): void
    {
        $page = $this->session->getPage();
        $list = $page->find('css', '[data-test-three-brs-passkey-list]');
        Assert::null($list, 'Expected no passkey list to be rendered.');
    }

    /**
     * @Then I should see an admin passkey labelled :label
     */
    public function iShouldSeeAnAdminPasskeyLabelled(string $label): void
    {
        $page = $this->session->getPage();
        $content = (string) $page->getContent();
        Assert::contains($content, $label, sprintf('Expected passkey label "%s" on the page.', $label));
    }

    /**
     * @When I remove the admin passkey :credentialId
     */
    public function iRemoveTheAdminPasskey(string $credentialId): void
    {
        $stored = $this->credentialRepository->findOneByCredentialId($credentialId);
        Assert::notNull($stored, sprintf('Passkey "%s" not stored.', $credentialId));

        $page = $this->session->getPage();
        $button = $page->find('css', sprintf('[data-test-three-brs-passkey-delete="%d"]', $stored->getId()));
        Assert::notNull($button, sprintf('Delete button for passkey "%s" not found.', $credentialId));
        $button->click();
    }

    protected function findAdminUser(string $email): AdminUserInterface
    {
        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        Assert::notNull($user, sprintf('Administrator "%s" not found.', $email));
        Assert::isInstanceOf($user, AdminUserInterface::class);

        return $user;
    }
}
