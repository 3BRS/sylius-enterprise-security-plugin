#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks that every assertion of absence in the Behat contexts is able to fail.
 *
 * An assertion that something did not happen is the easy one to get wrong, because
 * getting it wrong looks exactly like getting it right: the suite stays green. This
 * repository has shipped several - a page assertion reading the request it had just
 * sent rather than where it landed, a settings assertion reading a value Doctrine
 * still had in memory - and each was green for months.
 *
 * The check is to invert the assertion and insist the suite notices. `Assert::false`
 * becomes `Assert::true`, `null` becomes `notNull`, and so on; every scenario that
 * runs the step then has to fail. One that still passes is looking at something that
 * cannot vary.
 *
 * Two passes, because inverting everything at once is fast but blind: when a scenario
 * has two inverted steps the first stops it and the second never runs, which reads as
 * "did not fail" for a step that was never reached. So the first pass inverts
 * everything and runs the suite once, and the second pass takes whatever survived and
 * inverts it alone.
 *
 * Usage, from the project root or through `make assertion-sweep`:
 *
 *     php bin/assertion_sweep.php     # both passes over everything
 *     php bin/assertion_sweep.php --thorough
 *     php bin/assertion_sweep.php --suite=shop_lockout --filter=Shop/LockoutContext
 *
 * --thorough inverts each assertion of a multi-assertion method on its own, one suite
 * run apiece, which is worth narrowing first.
 *
 * --suite and --filter belong together. --suite narrows what runs, --filter (a
 * substring of the file path) narrows what is inverted; giving only the first reports
 * every method the chosen suite happens not to exercise, which is true and useless.
 *
 * The context tree is copied before anything is touched and put back afterwards,
 * including on failure and on Ctrl-C. Nothing is left modified.
 */

const CONTEXT_DIR = 'tests/Behat/Context';

/** Inverting these is what turns "did not happen" into "did happen". */
const INVERSIONS = [
    'Assert::false(' => 'Assert::true(',
    'Assert::null(' => 'Assert::notNull(',
    'Assert::notContains(' => 'Assert::contains(',
    'Assert::isEmpty(' => 'Assert::notEmpty(',
];

$root = dirname(__DIR__);
chdir($root);

if (!is_dir(CONTEXT_DIR)) {
    fwrite(STDERR, "Run this from the project root; " . CONTEXT_DIR . " is not here.\n");

    exit(1);
}

$arguments = array_slice($argv, 1);
$thorough = in_array('--thorough', $arguments, true);
$suite = null;
$filter = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--suite=')) {
        $suite = substr($argument, strlen('--suite='));
    }
    if (str_starts_with($argument, '--filter=')) {
        $filter = substr($argument, strlen('--filter='));
    }
}
$backup = sys_get_temp_dir() . '/assertion-sweep-' . getmypid();

restoreOnExit($backup);
copyTree(CONTEXT_DIR, $backup);

$targets = findAssertions($filter);
if ($targets === []) {
    echo "No assertion of absence found - nothing to check.\n";

    exit(0);
}

printf("%d method(s) carrying %d assertion(s) of absence.\n\n", count($targets), array_sum(array_map('count', $targets)));

echo "Pass 1: inverting all of them at once.\n";
foreach ($targets as $method => $assertions) {
    invert($assertions[0]['file'], $method, null);
}
$failedMethods = runSuiteAndCollectFailedMethods();
restore($backup);

$survivors = array_keys(array_diff_key($targets, array_flip($failedMethods)));
printf("  %d of %d noticed.\n\n", count($targets) - count($survivors), count($targets));

$blind = [];

if ($survivors !== []) {
    echo "Pass 2: the rest, one at a time - a scenario stopped by an earlier inversion never reached them.\n";
    foreach ($survivors as $method) {
        invert($targets[$method][0]['file'], $method, null);
        $noticed = in_array($method, runSuiteAndCollectFailedMethods(), true);
        restore($backup);

        printf("  %-58s %s\n", $method, $noticed ? 'noticed' : 'NOT NOTICED');
        if (!$noticed) {
            $blind[] = $method . '()';
        }
    }
    echo "\n";
}

if ($thorough) {
    echo "Thorough: each assertion of a multi-assertion method on its own.\n";
    foreach ($targets as $method => $assertions) {
        if (count($assertions) < 2) {
            continue;
        }

        foreach (array_keys($assertions) as $index) {
            invert($assertions[$index]['file'], $method, $index);
            $noticed = in_array($method, runSuiteAndCollectFailedMethods(), true);
            restore($backup);

            printf("  %-52s #%d  %s\n", $method, $index, $noticed ? 'noticed' : 'NOT NOTICED');
            if (!$noticed) {
                $blind[] = sprintf('%s() assertion #%d', $method, $index);
            }
        }
    }
    echo "\n";
}

if ($blind === []) {
    echo "Every assertion of absence can fail.\n";

    exit(0);
}

echo "These cannot fail - the suite is green whether the thing happened or not:\n";
foreach ($blind as $one) {
    echo '  ' . $one . "\n";
}

exit(1);

/**
 * Method name => list of assertions in it, in the order they appear.
 *
 * Keyed by method rather than by step text because that is what the Behat output
 * names, and because one method can answer to several step texts.
 *
 * @return array<string, list<array{file: string, offset: int, token: string}>>
 */
function findAssertions(?string $filter): array
{
    $found = [];
    $tokens = implode('|', array_map('preg_quote', array_keys(INVERSIONS)));

    /** @var iterable<SplFileInfo> $files */
    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CONTEXT_DIR)),
        '/\.php$/',
    );

    foreach ($files as $file) {
        if ($filter !== null && !str_contains($file->getPathname(), $filter)) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        preg_match_all(
            '/(?:public|protected|private) function (\w+)\(.*?\n(.*?)\n    \}/s',
            $source,
            $methods,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($methods as $method) {
            [$name] = $method[1];
            [$body, $bodyOffset] = $method[2];

            if (preg_match_all('/' . $tokens . '/', (string) $body, $hits, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($hits[0] as $hit) {
                $found[(string) $name][] = [
                    'file' => $file->getPathname(),
                    'offset' => (int) $bodyOffset + (int) $hit[1],
                    'token' => (string) $hit[0],
                ];
            }
        }
    }

    ksort($found);

    return $found;
}

/**
 * Inverts one assertion of a method, or all of them when $index is null.
 *
 * Offsets are recomputed here rather than taken from findAssertions(), because a
 * previous inversion in the same file would have moved them.
 */
function invert(string $file, string $method, ?int $index): void
{
    $source = (string) file_get_contents($file);

    if (preg_match('/(?:public|protected|private) function ' . preg_quote($method, '/') . '\(.*?\n(.*?)\n    \}/s', $source, $m, PREG_OFFSET_CAPTURE) !== 1) {
        fwrite(STDERR, sprintf("Could not find %s() in %s.\n", $method, $file));

        exit(1);
    }

    [$body, $offset] = $m[1];
    $body = (string) $body;

    if ($index === null) {
        $body = strtr($body, INVERSIONS);
    } else {
        $tokens = implode('|', array_map('preg_quote', array_keys(INVERSIONS)));
        preg_match_all('/' . $tokens . '/', $body, $hits, PREG_OFFSET_CAPTURE);
        [$token, $at] = $hits[0][$index];
        $body = substr_replace($body, INVERSIONS[$token], (int) $at, strlen((string) $token));
    }

    file_put_contents($file, substr_replace($source, $body, (int) $offset, strlen((string) $m[1][0])));
}

/**
 * @return list<string> context methods that a failing step ran through
 */
function runSuiteAndCollectFailedMethods(): array
{
    global $suite;

    exec(sprintf(
        'APP_ENV=test php -d memory_limit=1G vendor/bin/behat %s--no-interaction --format=pretty --no-colors 2>&1',
        $suite === null ? '' : '--suite=' . escapeshellarg($suite) . ' ',
    ), $output);

    $failed = [];
    $stepPattern = '/^\s+(?:Given|When|Then|And|But)\s+.*#\s+\S+::(\w+)\(\)\s*$/';

    foreach ($output as $i => $line) {
        if (preg_match($stepPattern, $line, $m) !== 1) {
            continue;
        }

        // Behat prints the failure directly under the step that raised it, indented
        // and not itself a step line.
        $next = trim($output[$i + 1] ?? '');
        if ($next !== '' && preg_match($stepPattern, $output[$i + 1]) !== 1 && !str_starts_with($next, 'Scenario') && !str_starts_with($next, 'Feature') && !str_starts_with($next, '@')) {
            $failed[] = $m[1];
        }
    }

    return array_values(array_unique($failed));
}

function copyTree(string $from, string $to): void
{
    exec(sprintf('rm -rf %s && cp -r %s %s', escapeshellarg($to), escapeshellarg($from), escapeshellarg($to)), $_, $status);

    if ($status !== 0) {
        fwrite(STDERR, "Could not take a copy of the contexts; refusing to modify them.\n");

        exit(1);
    }
}

function restore(string $backup): void
{
    exec(sprintf('rm -rf %s && cp -r %s %s', escapeshellarg(CONTEXT_DIR), escapeshellarg($backup), escapeshellarg(CONTEXT_DIR)));
}

/**
 * The contexts are rewritten in place, so every way out of this script has to put
 * them back - a sweep that exits owing the working tree a restore is worse than no
 * sweep.
 */
function restoreOnExit(string $backup): void
{
    register_shutdown_function(static function () use ($backup): void {
        if (is_dir($backup)) {
            restore($backup);
            exec('rm -rf ' . escapeshellarg($backup));
        }
    });

    if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM, SIGHUP] as $signal) {
            pcntl_signal($signal, static function (): void {
                exit(130);
            });
        }
    }
}
