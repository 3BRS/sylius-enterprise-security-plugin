<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\RawMinkContext;
use DMore\ChromeDriver\ChromeDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use ReflectionProperty;
use Sylius\Component\Core\Model\ShopUserInterface;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Webmozart\Assert\Assert;

/**
 * Drives the WebAuthn ceremony in a real browser.
 *
 * The rest of the suite runs on the kernel session, which cannot execute a line of
 * `public/js/passkey.js` — 230 of the plugin's 302 shipped JavaScript lines. What is
 * verified server-side elsewhere (the assertion crypto, the credential row) says
 * nothing about whether the browser half ever calls it.
 *
 * Chrome exposes software authenticators over CDP, so no hardware and no human
 * fingerprint are involved. `ChromeDriver::$page` is private with no accessor, hence
 * the reflection below: the driver exposes no other route to `send()`.
 */
class PasskeyBrowserContext extends RawMinkContext implements Context
{
    protected ?string $authenticatorId = null;

    /**
     * @param ObjectRepository<ShopUserInterface> $shopUserRepository
     */
    public function __construct(
        protected UserPasswordHasherInterface $userPasswordHasher,
        protected ObjectRepository $shopUserRepository,
        protected EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given a virtual authenticator is attached to the browser
     */
    public function aVirtualAuthenticatorIsAttached(): void
    {
        // The page target only exists once something has been visited.
        $this->getSession()->visit($this->locatePath('/'));

        $this->sendCommand('WebAuthn.enable');

        $result = $this->sendCommand('WebAuthn.addVirtualAuthenticator', [
            'options' => [
                'protocol' => 'ctap2',
                'transport' => 'internal',
                'hasResidentKey' => true,
                'hasUserVerification' => true,
                'isUserVerified' => true,
                'automaticPresenceSimulation' => true,
            ],
        ]);

        Assert::keyExists($result, 'authenticatorId');
        $this->authenticatorId = $result['authenticatorId'];
    }

    /**
     * The browser authenticates against the database, so the credential has to be a
     * hash this application accepts - set here rather than relying on how a fixture
     * step happened to store it.
     *
     * @Given the customer :email can sign in with the password :password
     */
    public function theCustomerCanSignInWith(string $email, string $password): void
    {
        $user = $this->shopUserRepository->findOneBy(['username' => $email]);
        Assert::isInstanceOf($user, ShopUserInterface::class, sprintf('No shop user %s.', $email));

        $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
        $this->entityManager->flush();

        Assert::true($this->credentialsAreValid($email, $password), 'The password did not take.');
    }

    /**
     * @When I sign in to the shop in the browser as :email with password :password
     */
    public function iSignInToTheShopInTheBrowser(string $email, string $password): void
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/en_US/login'));

        $this->waitForDocumentReady();
        $page = $session->getPage();

        // Located by input type rather than by field name: the names belong to Sylius'
        // login form, not to this plugin, and have moved between major versions.
        $passwordField = $page->find('css', 'form input[type="password"]');

        if ($passwordField === null) {
            throw new ExpectationException(sprintf(
                'No password input on the login page. url=%s status=%s body=%s',
                $session->getCurrentUrl(),
                (string) $session->getStatusCode(),
                mb_substr(preg_replace('/\s+/', ' ', strip_tags($page->getContent())) ?? '', 0, 600),
            ), $session->getDriver());
        }

        $identifierField = $page->find('css', 'form input[type="email"], form input[type="text"]');

        if ($identifierField === null) {
            throw new ExpectationException('No identifier input on the login page.', $session->getDriver());
        }

        $identifierField->setValue($email);
        $passwordField->setValue($password);

        $session->executeScript(
            "document.querySelector('form input[type=\"password\"]').form.submit();",
        );

        $this->waitForDocumentReady();

        if (str_contains($session->getCurrentUrl(), '/login')) {
            throw new ExpectationException(sprintf(
                'Still on the login page. credentialsValid=%s url=%s form=%s alerts=%s',
                var_export($this->credentialsAreValid($email, $password), true),
                $session->getCurrentUrl(),
                $session->evaluateScript(
                    "(() => { const f = document.querySelector('form input[type=\"password\"]')?.form;" .
                    'return f ? JSON.stringify({action: f.action, method: f.method, fields: ' .
                    "[...f.elements].map(e => e.name + ':' + e.type)}) : 'no form'; })()",
                ),
                $session->evaluateScript(
                    "[...document.querySelectorAll('.alert, .invalid-feedback, [role=alert]')]" .
                    ".map(e => e.textContent.trim()).join(' | ') || 'none'",
                ),
            ), $session->getDriver());
        }
    }

    /**
     * @When I register a passkey labelled :label in the browser
     */
    public function iRegisterAPasskeyInTheBrowser(string $label): void
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/en_US/account/passkey'));
        $this->waitForDocumentReady();

        // The dialog is a Bootstrap modal; if its JavaScript did not load, opening it
        // by hand still leaves the ceremony button clickable, which is what is under test.
        $session->executeScript(
            "document.querySelector('#three-brs-passkey-create-modal')" .
            "?.classList.add('show');" .
            "document.querySelector('#three-brs-passkey-create-modal')" .
            "?.setAttribute('style', 'display:block');",
        );

        $input = $session->getPage()->find('css', '#three_brs_passkey_label_input');

        if ($input === null) {
            throw new ExpectationException(sprintf(
                'No passkey label input. url=%s status=%s body=%s',
                $session->getCurrentUrl(),
                (string) $session->getStatusCode(),
                mb_substr(preg_replace('/\s+/', ' ', strip_tags($session->getPage()->getContent())) ?? '', 0, 500),
            ), $session->getDriver());
        }

        $input->setValue($label);
        $session->executeScript("document.querySelector('#three_brs_passkey_register_button').click();");

        $this->waitForPasskeyToAppear($label);
    }

    /**
     * @Then the browser should list a passkey labelled :label
     */
    public function theBrowserShouldListAPasskey(string $label): void
    {
        $list = $this->getSession()->getPage()->find('css', '[data-test-three-brs-passkey-list]');

        if ($list === null) {
            throw new ExpectationException('The passkey list is not on the page.', $this->getSession()->getDriver());
        }

        Assert::contains($list->getText(), $label);
    }

    protected function credentialsAreValid(string $email, string $password): bool
    {
        $user = $this->shopUserRepository->findOneBy(['username' => $email]);

        return $user instanceof ShopUserInterface
            && $this->userPasswordHasher->isPasswordValid($user, $password);
    }

    protected function waitForPasskeyToAppear(string $label): void
    {
        // The ceremony is asynchronous: options fetch, authenticator, verify, reload.
        $this->getSession()->wait(
            15000,
            sprintf(
                "document.querySelector('[data-test-three-brs-passkey-list]')" .
                '?.textContent.includes(%s) === true',
                json_encode($label, \JSON_THROW_ON_ERROR),
            ),
        );
    }

    protected function waitForDocumentReady(): void
    {
        $this->getSession()->wait(10000, "document.readyState === 'complete'");
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    protected function sendCommand(string $command, array $parameters = []): array
    {
        $driver = $this->getSession()->getDriver();

        if (!$driver instanceof ChromeDriver) {
            throw new RuntimeException(sprintf(
                'A virtual authenticator needs the Chrome session; got %s. Tag the scenario @javascript.',
                $driver::class,
            ));
        }

        $page = new ReflectionProperty(ChromeDriver::class, 'page');
        $page->setAccessible(true);

        return $page->getValue($driver)->send($command, $parameters);
    }
}
