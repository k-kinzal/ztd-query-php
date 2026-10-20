<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class InspectionMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d'];
            foreach (['plain' => 'a INT', 'primary_key' => 'a INT PRIMARY KEY', 'unique_key' => 'a INT NOT NULL UNIQUE', 'nullable_unique' => 'a INT UNIQUE'] as $name => $columns) {
                $statements[] = 'CREATE TABLE ' . $name . '(' . $columns . ')';
                $statements[] = 'SHOW FULL COLUMNS FROM ' . $name;
                $statements[] = 'SHOW INDEX FROM ' . $name;
                if (!str_starts_with($release, '5.')) {
                    $statements[] = 'SHOW EXTENDED FULL COLUMNS FROM ' . $name;
                }
            }
            foreach (['MEMORY', 'MyISAM'] as $engine) {
                $statements[] = 'CREATE TABLE ' . $engine . '_table(a INT,KEY(a)) ENGINE=' . $engine;
                $statements[] = 'SHOW FULL COLUMNS FROM ' . $engine . '_table';
                $statements[] = 'SHOW INDEX FROM ' . $engine . '_table';
            }
            array_push($statements, 'CREATE TEMPORARY TABLE tmp(a INT,KEY(a))', 'SHOW FULL COLUMNS FROM tmp', 'SHOW INDEX FROM tmp');
            if (!str_starts_with($release, '5.')) {
                array_push($statements, 'SHOW EXTENDED FULL COLUMNS FROM tmp', "SHOW EXTENDED FULL COLUMNS FROM plain LIKE 'DB%'", 'SHOW EXTENDED COLUMNS FROM plain');
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Schema fixtures and inspection statements
     */
    #[DataProvider('providerInputs')]
    public function testInspectionRowsAndEveryProtocolColumnFieldMatchNativeQueries(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $memory->set_charset('utf8mb4');
                $observer = new Native();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
