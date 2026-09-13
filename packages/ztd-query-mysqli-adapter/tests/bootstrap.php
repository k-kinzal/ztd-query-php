<?php

declare(strict_types=1);

use Containers\MySql80Container;
use Containers\MySql84Container;
use Testcontainers\Testcontainers;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (getenv('ZTD_TEST_MYSQL_HOST') === false || getenv('ZTD_TEST_MYSQL_PORT') === false) {
    $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
    $port = $container->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.');
    $host = str_replace('localhost', '127.0.0.1', $container->getHost());
    putenv('ZTD_TEST_MYSQL_HOST=' . $host);
    putenv('ZTD_TEST_MYSQL_PORT=' . $port);
    $_SERVER['ZTD_TEST_MYSQL_HOST'] = $host;
    $_ENV['ZTD_TEST_MYSQL_HOST'] = $host;
    $_SERVER['ZTD_TEST_MYSQL_PORT'] = (string) $port;
    $_ENV['ZTD_TEST_MYSQL_PORT'] = (string) $port;
}
