<?php

declare(strict_types=1);

use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Tests\ThreeBRS\SyliusEnterpriseSecurityPlugin\Kernel;

/*
 * Front controller for @javascript Behat scenarios.
 *
 * A browser driven over CDP talks to nginx, not to the kernel Behat booted, so the
 * two only share state when both run in the same environment against the same
 * database. index.php serves whatever APP_ENV the php container carries (dev), which
 * would leave the browser looking at sylius_dev while fixtures are written to
 * sylius_test. Forcing the environment here is what makes the two halves agree.
 *
 * Set before bootstrap.php runs: Dotenv::bootEnv() keeps an APP_ENV that is already
 * present and loads .env.test on top of it.
 */
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';

require dirname(__DIR__) . '/config/bootstrap.php';

umask(0000);
Debug::enable();

$kernel = new Kernel('test', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
