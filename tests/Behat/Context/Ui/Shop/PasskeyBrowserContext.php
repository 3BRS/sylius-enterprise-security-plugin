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
    protected const NAVIGATION_TIMEOUT_SECONDS = 10;

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

        // Chrome outlives the scenario, and so do the authenticators added to it - with
        // the resident credentials they hold. The database does not: it is purged
        // between scenarios, so a credential minted for an earlier scenario names a user
        // id nobody has any more. Left in place it is still offered at sign-in, and the
        // server rejects it. Disabling drops every authenticator and starts this
        // scenario with an empty one.
        $this->sendCommand('WebAuthn.disable');
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
        $user = $this->shopUserRepository->findOneBy(['usernameCanonical' => strtolower($email)]);
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

        $this->waitForNavigationAwayFrom('/login');

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
        $this->captureBrowserAlerts();
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

        Assert::contains(
            $list->getText(),
            $label,
            sprintf('The passkey list does not name %s. alerts=%s', $label, $this->capturedBrowserAlerts()),
        );
    }

    /**
     * @When I sign out in the browser
     */
    public function iSignOutInTheBrowser(): void
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/en_US/logout'));
        $this->waitForDocumentReady();

        // Checked rather than assumed. If the session outlived the logout, the ceremony
        // that follows would be performed by a browser that is already signed in, and
        // would pass without proving anything about signing in with a passkey.
        $session->visit($this->locatePath('/en_US/account/dashboard'));
        $this->waitForDocumentReady();

        if (!str_contains($session->getCurrentUrl(), '/login')) {
            throw new ExpectationException(sprintf(
                'Still signed in after logout: the account dashboard answered at %s.',
                $session->getCurrentUrl(),
            ), $session->getDriver());
        }
    }

    /**
     * @When I sign in to the shop in the browser with a passkey
     */
    public function iSignInToTheShopInTheBrowserWithAPasskey(): void
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/en_US/login'));
        $this->waitForDocumentReady();

        if ($session->getPage()->find('css', '#three_brs_passkey_login_button') === null) {
            throw new ExpectationException(sprintf(
                'No passkey login button on the login page. url=%s status=%s body=%s',
                $session->getCurrentUrl(),
                (string) $session->getStatusCode(),
                mb_substr(preg_replace('/\s+/', ' ', strip_tags($session->getPage()->getContent())) ?? '', 0, 500),
            ), $session->getDriver());
        }

        $this->captureBrowserAlerts();
        $session->executeScript("document.getElementById('three_brs_passkey_login_button').click();");

        $this->waitForNavigationAwayFrom('/login');

        if (str_contains($session->getCurrentUrl(), '/login')) {
            throw new ExpectationException(sprintf(
                'The passkey ceremony did not sign the browser in. url=%s alerts=%s',
                $session->getCurrentUrl(),
                $this->capturedBrowserAlerts(),
            ), $session->getDriver());
        }
    }

    /**
     * @Then I should be signed in to the shop in the browser as :email
     */
    public function iShouldBeSignedInToTheShopInTheBrowser(string $email): void
    {
        $session = $this->getSession();
        $session->visit($this->locatePath('/en_US/account/dashboard'));
        $this->waitForDocumentReady();

        if (str_contains($session->getCurrentUrl(), '/login')) {
            throw new ExpectationException(sprintf(
                'Not signed in: the account dashboard sent the browser to %s.',
                $session->getCurrentUrl(),
            ), $session->getDriver());
        }

        // The dashboard is behind the firewall, so reaching it proves a session exists;
        // the address proves whose it is, which is the half a shared session would hide.
        Assert::contains($session->getPage()->getText(), $email);
    }

    /**
     * passkey.js reports every failure with window.alert. A driven browser cannot
     * dismiss that on its own, so a failed ceremony would stop on a modal dialog and the
     * step would time out with nothing to say. Replacing alert captures the message
     * instead, which is then the reason the failure report can give.
     */
    protected function captureBrowserAlerts(): void
    {
        $this->getSession()->executeScript(
            'window.__threeBrsAlerts = [];' .
            'window.alert = function (message) { window.__threeBrsAlerts.push(message); };',
        );
    }

    protected function capturedBrowserAlerts(): string
    {
        return (string) $this->getSession()->evaluateScript(
            "(window.__threeBrsAlerts || []).join(' | ') || 'none'",
        );
    }

    protected function credentialsAreValid(string $email, string $password): bool
    {
        $user = $this->shopUserRepository->findOneBy(['usernameCanonical' => strtolower($email)]);

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
     * `form.submit()` returns before the browser starts navigating, and the document it
     * is about to leave is still `complete` - so waiting on readyState alone measures
     * the page we are trying to get away from and returns at once. On a fast machine
     * the navigation wins that race anyway; on a loaded CI runner it does not, and the
     * step reports a sign-in failure that never happened. Watch the location instead.
     */
    protected function waitForNavigationAwayFrom(string $urlFragment): void
    {
        $session = $this->getSession();
        $deadline = microtime(true) + self::NAVIGATION_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            if (!str_contains($session->getCurrentUrl(), $urlFragment)) {
                $this->waitForDocumentReady();

                return;
            }

            usleep(100000);
        }
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
