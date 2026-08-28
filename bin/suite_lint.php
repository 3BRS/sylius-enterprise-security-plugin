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
 * It also holds the manual test plan and the suite to each other: §5 of
 * docs/manual-test-plan.md numbers 73 scenarios T01-T73, and each has to be either
 * claimed by a scenario tagged @Tnn or named below as one nothing automates, with the
 * reason. Coverage counted by reading is a number that stops being true the day after
 * somebody writes it down; this one is recomputed every run.
 *
 * Usage, from the project root or through `make suite-lint`:
 *
 *     php bin/suite_lint.php
 *
 * Cheap enough to belong in CI, unlike bin/assertion_sweep.php next to it.
 */

const PLAN = 'docs/manual-test-plan.md';

/**
 * Scenarios from §5 that no Behat scenario claims, and why. Being on this list is a
 * statement, not an excuse: it says somebody looked. Automating one means deleting its
 * line here and tagging a scenario instead.
 */
const NOT_AUTOMATED = [
    'T02' => 'the boundary itself is PasswordPolicyValidatorTest::testPasswordExactlyAtMinLengthPassesValidation; the shop scenario registers thirteen characters, not eight',
    'T05' => 'max_length lives in PasswordPolicyValidatorTest (too long, and exactly at the maximum); no scenario configures it',
    'T07' => 'nothing cycles a password out of the history window and back in',
    'T09' => 'nothing checks that a customer history and an administrator history stay apart',
    'T12' => 'expiry counted from createdAt for an account that never changed its password - the case that decides whether enabling the feature expires everyone at once',
    'T18' => 'two_factor_authentication.mode: disabled - the pages it should take away are not in the feature-toggle route map either',
    'T27' => 'needs a second browser with its own cookies; the BrowserKit client has one jar',
    'T28' => 'a disabled OAuth provider is written in a settings scenario, but no sign-in is attempted against it afterwards',
    'T33' => 'auto_register_allowed_email_domains left empty, so an administrator must not be created',
    'T48' => 'auto_unlock_after: null, a lockout that never expires on its own',
    'T50' => 'the password-reset limiter; only the login one has a scenario',
    'T51' => 'the magic-link limiter; only the login one has a scenario',
    'T59' => 'a second sign-in from a genuinely different User-Agent, which has to notify again',
    'T63' => 'cancelling a deletion request that has already been carried out',
    'T73' => 'password expiration while password login is switched off',
];

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

echo "\nScenarios of the plan that nothing claims\n";
report(planCoverageProblems(), $problems, sprintf(
    '  none - %d tagged, %d declared not automated.',
    count(taggedPlanScenarios()),
    count(NOT_AUTOMATED),
));

echo "\n";

if ($problems === []) {
    echo "Every scenario is run, every step belongs to one context, and the plan is accounted for.\n";

    exit(0);
}

printf("%d problem(s).\n", count($problems));

exit(1);

/**
 * Every T-id §5 of the plan defines, against the tags the feature files carry.
 *
 * @return list<string>
 */
function planCoverageProblems(): array
{
    $plan = (string) file_get_contents(PLAN);
    $section = substr($plan, (int) strpos($plan, '## 5. Scenarios'), (int) strpos($plan, '## 6. Combinations') - (int) strpos($plan, '## 5. Scenarios'));

    preg_match_all('/^\|\s*(T\d\d)\s*\|/m', $section, $m);
    $declared = array_unique($m[1]);
    sort($declared);

    $tagged = taggedPlanScenarios();
    $problems = [];

    foreach ($declared as $id) {
        $isTagged = isset($tagged[$id]);
        $isDeclaredManual = array_key_exists($id, NOT_AUTOMATED);

        if (!$isTagged && !$isDeclaredManual) {
            $problems[] = sprintf('%s has no scenario tagged @%s and is not on the not-automated list', $id, $id);
        }

        if ($isTagged && $isDeclaredManual) {
            $problems[] = sprintf('%s is tagged on %s and also listed as not automated - drop it from the list', $id, $tagged[$id]);
        }
    }

    foreach (array_keys($tagged) as $id) {
        if (!in_array($id, $declared, true)) {
            $problems[] = sprintf('@%s is on %s but §5 of the plan does not define it', $id, $tagged[$id]);
        }
    }

    foreach (array_keys(NOT_AUTOMATED) as $id) {
        if (!in_array($id, $declared, true)) {
            $problems[] = sprintf('%s is on the not-automated list but §5 of the plan does not define it', $id);
        }
    }

    return $problems;
}

/**
 * @return array<string, string> T-id => the feature file that claims it
 */
function taggedPlanScenarios(): array
{
    $tagged = [];

    /** @var iterable<SplFileInfo> $files */
    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FEATURES_DIR)),
        '/\.feature$/',
    );

    foreach ($files as $file) {
        preg_match_all('/@(T\d\d)\b/', (string) file_get_contents($file->getPathname()), $m);

        foreach ($m[1] as $id) {
            $tagged[$id] = $file->getPathname();
        }
    }

    return $tagged;
}

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
