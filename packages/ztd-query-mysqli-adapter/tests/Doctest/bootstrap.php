<?php

declare(strict_types=1);

$host = getenv('ZTD_TEST_MYSQL_HOST');
$port = getenv('ZTD_TEST_MYSQL_PORT');
if ($host === false || $port === false) {
    throw new RuntimeException('The shared test container has not been started.');
}
$connection = new mysqli($host, 'root', 'root', '', (int) $port);
$database = 'ztd_' . bin2hex(random_bytes(8));
$connection->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4');
putenv('ZTD_EXAMPLE_HOST=' . $host);
putenv('ZTD_EXAMPLE_PORT=' . $port);
putenv('ZTD_EXAMPLE_DATABASE=' . $database);
