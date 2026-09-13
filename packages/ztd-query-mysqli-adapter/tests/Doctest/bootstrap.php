<?php

declare(strict_types=1);

use Containers\MySql80Container;
use Containers\MySql84Container;
use Testcontainers\Testcontainers;

$container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
$host = str_replace('localhost', '127.0.0.1', $container->getHost());
$port = $container->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.');
$connection = new mysqli($host, 'root', 'root', '', $port);
$database = 'ztd_' . bin2hex(random_bytes(8));
$connection->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
putenv('ZTD_EXAMPLE_HOST=' . $host);
putenv('ZTD_EXAMPLE_PORT=' . $port);
putenv('ZTD_EXAMPLE_DATABASE=' . $database);
