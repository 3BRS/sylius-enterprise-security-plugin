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
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\ForeignObjectProbeTrait;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Entity\CustomerPasskeyCredential;
use ThreeBRS\SyliusEnterpriseSecurityPlugin\Repository\CustomerPasskeyCredentialRepositoryInterface;
use Webmozart\Assert\Assert;

class PasskeyContext implements Context
{
    use ForeignObjectProbeTrait;

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
        $this->session->visit($this->router->generate('three_brs_shop_passkey_index', ['_locale' => 'en_US']));
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

    /**
     * @Then I should not see a passkey labelled :label
     */
    public function iShouldNotSeeAPasskeyLabelled(string $label): void
    {
        $content = (string) $this->session->getPage()->getContent();
        Assert::notContains($content, $label, sprintf('Another account\'s passkey "%s" is listed on this page.', $label));
    }

    /**
     * Posts the delete straight at another account's credential id, carrying this
     * account's own CSRF token — the page never offers such a button, so nothing
     * short of the request itself can show whether the controller checks who owns
     * the credential or only that the id exists.
     *
     * @When I try to remove the passkey :credentialId that belongs to somebody else
     */
    public function iTryToRemoveThePasskeyThatBelongsToSomebodyElse(string $credentialId): void
    {
        $stored = $this->credentialRepository->findOneByCredentialId($credentialId);
        Assert::notNull($stored, sprintf('Passkey "%s" not stored.', $credentialId));

        $this->postAtForeignId(
            $this->router->generate('three_brs_shop_passkey_delete', ['_locale' => 'en_US', 'id' => $stored->getId()]),
            'three-brs-shop-passkey-delete-modal-',
        );
    }

    /**
     * @Then the passkey :credentialId should still exist
     */
    public function thePasskeyShouldStillExist(string $credentialId): void
    {
        $this->entityManager->clear();

        Assert::notNull(
            $this->credentialRepository->findOneByCredentialId($credentialId),
            sprintf('Passkey "%s" was removed by somebody who does not own it.', $credentialId),
        );
    }

    /**
     * @Then the request should have been refused as not found
     */
    public function theRequestShouldHaveBeenRefusedAsNotFound(): void
    {
        $this->assertRefusedAsNotFound();
    }

    /**
     * Takes the token out of one of this account's own confirm forms, so the
     * request is refused for the reason under test rather than for a missing
     * token.
     */
    protected function getSession(): Session
    {
        return $this->session;
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
