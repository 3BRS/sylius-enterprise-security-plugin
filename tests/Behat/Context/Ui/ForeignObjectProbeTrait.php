<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Webmozart\Assert\Assert;

/**
 * Posts a state-changing request at an id that belongs to somebody else.
 *
 * The page never renders such a button, so nothing short of the request itself
 * shows whether the controller checks who owns the row or only that the id
 * exists. Both combination scenarios that need this (K16 in
 * docs/manual-test-plan.md) drive it the same way, which is why it lives here.
 */
trait ForeignObjectProbeTrait
{
    abstract protected function getSession(): Session;

    /**
     * @param string $ownModalPrefix the confirm buttons this account does get, to
     *                               take a valid token from
     */
    protected function postAtForeignId(string $url, string $ownModalPrefix): void
    {
        $session = $this->getSession();

        // The token comes from the form behind one of this account's own confirm
        // buttons, not from the first form on the page: a page can carry several
        // forms under different token ids, and the wrong one would be refused as a
        // bad token rather than for the reason under test. The page only ever
        // renders this account's own rows, so any of them will do.
        $button = $session->getPage()->find('css', sprintf('[data-test-three-brs-modal-confirm^="%s"]', $ownModalPrefix));
        Assert::notNull($button, sprintf(
            'No confirm button starting with "%s" on the page — this account needs a row of its own for a token to be rendered.',
            $ownModalPrefix,
        ));

        $input = $button->find('xpath', 'ancestor::form//input[@name="_csrf_token"]');
        Assert::notNull($input, 'The form behind the confirm button carries no CSRF token.');

        $driver = $session->getDriver();
        Assert::isInstanceOf($driver, BrowserKitDriver::class, 'This step needs the BrowserKit session.');

        // Straight at the driver's own client: `test.client` is declared
        // share(false), so a client injected anywhere else would carry a different
        // session and be anonymous here.
        $driver->getClient()->request('POST', $url, ['_csrf_token' => (string) $input->getValue()]);
    }

    protected function assertRefusedAsNotFound(): void
    {
        $status = $this->getSession()->getStatusCode();

        Assert::same(404, $status, sprintf('Expected the foreign id to be refused with 404, got %d.', $status));
    }
}
