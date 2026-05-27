<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use Webmozart\Assert\Assert;

class PasskeyContext implements Context
{
    public function __construct(
        protected Session $session,
        protected CustomerRepositoryInterface $customerRepository,
        protected CustomerPasskeyCredentialRepositoryInterface $credentialRepository,
        protected EntityManagerInterface $entityManager,
        protected UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @When I visit the passkey management page
     */
    public function iVisitThePasskeyManagementPage(): void
    {
        $this->session->visit($this->router->generate('three_brs_shop_passkey_index'));
    }

    /**
     * @Given a passkey :credentialId labelled :label exists for :email
     */
    public function aPasskeyExistsFor(string $credentialId, string $label, string $email): void
    {
        $shopUser = $this->findShopUser($email);

        $credential = new CustomerPasskeyCredential();
        $credential->setShopUser($shopUser);
        $credential->setCredentialId($credentialId);
        $credential->setLabel($label);
        $credential->setCredentialSource([
            'publicKeyCredentialId' => base64_encode($credentialId),
            'type' => 'public-key',
            'transports' => ['internal'],
            'attestationType' => 'none',
            'trustPath' => ['type' => 'Webauthn\\TrustPath\\EmptyTrustPath'],
            'aaguid' => Uuid::v4()->toRfc4122(),
            'credentialPublicKey' => '',
            'userHandle' => base64_encode((string) $shopUser->getId()),
            'counter' => 0,
        ]);

        $this->entityManager->persist($credential);
        $this->entityManager->flush();
    }

    /**
     * @Then I should see no registered passkeys
     */
    public function iShouldSeeNoRegisteredPasskeys(): void
    {
        $page = $this->session->getPage();
        $list = $page->find('css', '[data-test-three-brs-passkey-list]');
        Assert::null($list, 'Expected no passkey list to be rendered.');
    }

    /**
     * @Then I should see a passkey labelled :label
     */
    public function iShouldSeeAPasskeyLabelled(string $label): void
    {
        $page = $this->session->getPage();
        $content = (string) $page->getContent();
        Assert::contains($content, $label, sprintf('Expected passkey label "%s" on the page.', $label));
    }

    /**
     * @When I remove the passkey :credentialId
     */
    public function iRemoveThePasskey(string $credentialId): void
    {
        $stored = $this->credentialRepository->findOneByCredentialId($credentialId);
        Assert::notNull($stored, sprintf('Passkey "%s" not stored.', $credentialId));

        $page = $this->session->getPage();
        $modalId = 'three-brs-shop-passkey-delete-modal-' . $stored->getId();
        $button = $page->find('css', sprintf('[data-test-three-brs-modal-confirm="%s"]', $modalId));
        Assert::notNull($button, sprintf('Delete confirm button for passkey "%s" not found in modal.', $credentialId));
        $button->click();
    }

    protected function findShopUser(string $email): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => strtolower($email)]);
        Assert::notNull($customer, sprintf('Customer "%s" not found.', $email));

        $user = $customer->getUser();
        Assert::notNull($user);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }
}
