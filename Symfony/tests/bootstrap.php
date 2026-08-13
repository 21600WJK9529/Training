<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env', 'test');
}

$testDatabaseUrl = sprintf('sqlite:///%s/var/data_test.db', dirname(__DIR__));
$_SERVER['DATABASE_URL'] = $testDatabaseUrl;
$_ENV['DATABASE_URL'] = $testDatabaseUrl;
putenv(sprintf('DATABASE_URL=%s', $testDatabaseUrl));

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
