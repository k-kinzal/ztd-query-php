<?php

declare(strict_types=1);

use Tests\Fixtures\MySqlContainer;

[$database, $connection] = MySqlContainer::createTestDatabase();
[$host, , , , $port] = MySqlContainer::connectionParameters();
putenv('ZTD_EXAMPLE_HOST=' . $host);
putenv('ZTD_EXAMPLE_PORT=' . $port);
putenv('ZTD_EXAMPLE_DATABASE=' . $database);
register_shutdown_function(static function () use ($connection, $database): void {
    $connection->query('DROP DATABASE IF EXISTS `' . $database . '`');
});
