#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that every scenario is actually run by some suite, and that no suite is
 * broken by two contexts claiming the same step.
 *
 * Both failures are silent. A suite file that nobody imported into suites.yml is not
 * an error to Behat - it simply does not exist, the feature file it would have run
 * looks alive, and the totals Behat prints are of what it loaded. Three suites sat
 * like that in this repository and five scenarios had never run, on any machine or
 * any leg of CI. A step defined twice is louder, but only once the suite runs, and
 * only for whoever runs that suite.
 *
 * Both are answered by a dry run, which matches every step without executing one and
 * takes a few seconds. Ambiguity shows up in its output; scenarios nobody runs do not,
 * so those are found by asking Behat what it would run and comparing that against the
 * feature files on disk.
 *
 * Usage, from the project root or through `make suite-lint`:
 *
 *     php bin/suite_lint.php
 *
 * Cheap enough to belong in CI, unlike bin/assertion_sweep.php next to it.
 */

const SUITES_DIR = 'tests/Behat/Resources/suites';
const SUITES_FILE = 'tests/Behat/Resources/suites.yml';
const FEATURES_DIR = 'features';

/** Every profile a scenario could be reached through; see behat.yml.dist. */
const PROFILES = ['default', 'javascript'];

chdir(dirname(__DIR__));

$problems = [];

foreach ([SUITES_DIR, SUITES_FILE, FEATURES_DIR] as $path) {
    if (!file_exists($path)) {
        fwrite(STDERR, sprintf("Run this from the project root; %s is not here.\n", $path));

        exit(1);
    }
}

echo "Suite files that suites.yml never imports\n";
$unimported = findUnimportedSuites();
report($unimported, $problems, '  none.');

echo "\nSteps two contexts both answer to\n";
$ambiguous = [];
$reachable = [];

foreach (PROFILES as $profile) {
    [$output, $status] = dryRun($profile);

    foreach ($output as $line) {
        if (str_contains($line, 'is already defined in')) {
            $ambiguous[trim($line)] = true;
        }

        // "  Scenario: something # features/shop/x.feature:12"
        if (preg_match('/^\s*Scenario:.*#\s*(\S+\.feature:\d+)\s*$/', $line, $m) === 1) {
            $reachable[$m[1]] = true;
        }
    }

    // A dry run that fails without saying "already defined" is something else - a
    // missing step definition, a broken suite - and is worth passing on rather than
    // swallowing, so it counts as a problem too.
    if ($status !== 0 && $ambiguous === []) {
        $problems[] = sprintf('profile "%s" could not complete a dry run; run it by hand to see why', $profile);
    }
}

report(array_keys($ambiguous), $problems, '  none.');

echo "\nScenarios no suite runs\n";
$unreachable = array_values(array_diff(allScenariosOnDisk(), array_keys($reachable)));
sort($unreachable);
report($unreachable, $problems, '  none.');

echo "\n";

if ($problems === []) {
    echo "Every scenario is run and every step belongs to one context.\n";

    exit(0);
}

printf("%d problem(s).\n", count($problems));

exit(1);

/**
 * @param list<string> $found
 * @param list<string> $problems
 */
function report(array $found, array &$problems, string $whenEmpty): void
{
    if ($found === []) {
        echo $whenEmpty . "\n";

        return;
    }

    foreach ($found as $one) {
        echo '  ' . $one . "\n";
        $problems[] = $one;
    }
}

/**
 * @return list<string>
 */
function findUnimportedSuites(): array
{
    $imports = (string) file_get_contents(SUITES_FILE);
    $missing = [];

    foreach (glob(SUITES_DIR . '/*.yml') ?: [] as $suite) {
        if (!str_contains($imports, 'suites/' . basename($suite))) {
            $missing[] = $suite . ' is never imported, so nothing it defines runs';
        }
    }

    return $missing;
}

/**
 * @return array{0: list<string>, 1: int}
 */
function dryRun(string $profile): array
{
    exec(
        sprintf(
            'APP_ENV=test php -d memory_limit=1G vendor/bin/behat -p %s --dry-run --no-interaction --format=pretty --no-colors 2>&1',
            escapeshellarg($profile),
        ),
        $output,
        $status,
    );

    return [$output, $status];
}

/**
 * Every scenario in the feature files, as `path:line` - the same shape Behat prints,
 * so the two sets can be compared directly.
 *
 * @return list<string>
 */
function allScenariosOnDisk(): array
{
    $scenarios = [];

    /** @var iterable<SplFileInfo> $files */
    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FEATURES_DIR)),
        '/\.feature$/',
    );

    foreach ($files as $file) {
        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $number => $line) {
            if (preg_match('/^\s*Scenario(?: Outline)?:/', $line) === 1) {
                $scenarios[] = $file->getPathname() . ':' . ($number + 1);
            }
        }
    }

    sort($scenarios);

    return $scenarios;
}
