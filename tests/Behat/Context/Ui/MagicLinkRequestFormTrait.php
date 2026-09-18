<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Context\Ui;

use Behat\Mink\Session;
use Webmozart\Assert\Assert;

/**
 * Submits the shop's magic-link request form.
 *
 * Two suites post it for different reasons — the magic-link scenarios care about the
 * email that comes out, the rate-limit ones (T51) about the fourth request being
 * refused — and they cannot share a context, because each suite brings its own. The
 * step wording therefore differs per context; the request does not, so it lives here.
 */
trait MagicLinkRequestFormTrait
{
    abstract protected function getSession(): Session;

    protected function submitMagicLinkRequestForm(string $email): void
    {
        $session = $this->getSession();
        $session->visit('/magic-link');

        $page = $session->getPage();

        $input = $page->find('css', '#three_brs_magic_link_request_email');
        Assert::notNull($input, 'Magic link email input not found.');
        $input->setValue($email);

        $submit = $page->find('css', '#three_brs_magic_link_request_submit');
        Assert::notNull($submit, 'Magic link submit button not found.');
        $submit->click();
    }
}
