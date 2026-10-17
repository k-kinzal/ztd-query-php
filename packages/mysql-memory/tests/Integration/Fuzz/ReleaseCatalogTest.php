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
final class ReleaseCatalogTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testCatalogsAndColumnReferencesMatchEachRelease(string $release): void
    {
        $statements = [
            'SHOW PLUGINS', 'SHOW PRIVILEGES', 'SHOW GRANTS FOR CURRENT_USER',
            "INSTALL PLUGIN absent_plugin SONAME 'text'",
            'CREATE DATABASE d', 'USE d', 'CREATE TABLE no_columns (KEY(a))',
            'CREATE TABLE missing_reference(a INT REFERENCES absent_parent(a))',
            'DROP TABLE IF EXISTS missing_reference',
            'CREATE TABLE parent(a INT PRIMARY KEY)', 'INSERT INTO parent VALUES(1)',
            'CREATE TABLE child(a INT REFERENCES parent(a))',
            'INSERT INTO child VALUES(2)', 'INSERT INTO child VALUES(1)',
            'SHOW CREATE TABLE child', 'SELECT * FROM child ORDER BY a',
            'DROP TABLE child',
            'CREATE TABLE malformed(a INT REFERENCES parent)',
            'DROP TABLE IF EXISTS malformed',
            'CREATE TABLE child(a INT)', 'INSERT INTO child VALUES(1)',
            'ALTER TABLE child ADD b INT REFERENCES parent(a)',
            'INSERT INTO child VALUES(2,2)', 'SHOW CREATE TABLE child',
            'LOCK TABLE missing LOW_PRIORITY WRITE', 'UNLOCK TABLES',
            'ALTER EVENT missing DISABLE ON SLAVE',
            'RESET MASTER', 'RESET MASTER TO 1',
            'PURGE MASTER LOGS TO \'text\'', "PURGE MASTER LOGS BEFORE '2000-01-01'",
            'SHOW MASTER LOGS', 'SHOW MASTER STATUS',
            'RESET MASTER TO 0', 'PURGE MASTER LOGS TO', 'SHOW MASTER LOGS BOGUS',
        ];
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $native->query("DELETE FROM mysql.proxies_priv WHERE User = 'root' AND Host = '%'");
                $native->query('FLUSH PRIVILEGES');
                $password = str_starts_with($release, '5.') ? "SET PASSWORD FOR 'root'@'%' = PASSWORD('')" : "ALTER USER 'root'@'%' IDENTIFIED BY ''";
                $native->query($password);
                $memory->query($password);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
