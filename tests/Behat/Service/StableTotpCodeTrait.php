<?php

declare(strict_types=1);

namespace Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Behat\Service;

use OTPHP\TOTP;

trait StableTotpCodeTrait
{
    /**
     * Returns a TOTP code that is still valid by the time the form reaches the server.
     * If we are within 3 s of the 30-second window rollover, wait until the next
     * window starts so the generation and the server-side verify happen in the same window.
     * Without this, suite jitter (e.g. DeadlineTimingPadding from the magic-link
     * handlers) can push code generation across a window boundary and the server-side
     * verify (which uses no leeway) rejects the code, causing flaky failures.
     */
    protected function generateStableTotpCode(string $secret): string
    {
        $secondsIntoWindow = time() % 30;
        if ($secondsIntoWindow >= 27) {
            sleep(30 - $secondsIntoWindow + 1);
        }

        return TOTP::createFromSecret($secret)->now();
    }
}
