<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabaseEncryptionStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            "CREATE DATABASE d ENCRYPTION 'Y'", 'SHOW CREATE DATABASE d',
            "SELECT DEFAULT_ENCRYPTION FROM information_schema.schemata WHERE schema_name='d'",
            'CREATE TABLE d.t(a INT)', "CREATE TABLE d.t(a INT) ENCRYPTION='N'",
            'DROP TABLE IF EXISTS d.t', 'CREATE TEMPORARY TABLE d.t(a INT)',
            'DROP TEMPORARY TABLE IF EXISTS d.t', "ALTER DATABASE d ENCRYPTION 'N'",
            'SET SESSION default_table_encryption=ON', 'CREATE DATABASE e',
            'SHOW CREATE DATABASE e', 'ALTER DATABASE d CHARACTER SET latin1',
            'SHOW CREATE DATABASE d', 'CREATE TABLE d.t(a INT)', 'DROP TABLE IF EXISTS d.t',
            'CREATE TABLE e.t(a INT) ENGINE=MyISAM', 'DROP TABLE IF EXISTS e.t',
            "CREATE TABLE e.t(a INT) ENCRYPTION=''", 'SET SESSION default_table_encryption=OFF',
            "CREATE DATABASE f ENCRYPTION 'Y'",
        ];
        foreach (['', ' ENGINE=MyISAM', ' ENGINE=MEMORY'] as $engine) {
            foreach (['', 'TEMPORARY '] as $temporary) {
                foreach (['', " ENCRYPTION='N'", " ENCRYPTION='Y'", " ENCRYPTION=''", " ENCRYPTION='bad'"] as $option) {
                    $statements[] = 'CREATE ' . $temporary . 'TABLE f.t(a INT)' . $engine . $option;
                    $statements[] = 'DROP ' . $temporary . 'TABLE IF EXISTS f.t';
                }
            }
        }
        $legacy = ['CREATE DATABASE d', 'USE d'];
        foreach (['', 'TEMPORARY '] as $temporary) {
            foreach (['', 'N', 'Y', 'bad'] as $value) {
                $legacy[] = 'CREATE ' . $temporary . "TABLE t(a INT) ENCRYPTION='" . $value . "'";
                $legacy[] = 'DROP ' . $temporary . 'TABLE IF EXISTS t';
            }
        }
        yield '5.7.44' => ['5.7.44', $legacy];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The setting histories and table operations to compare
     */
    #[DataProvider('providerReleases')]
    public function testEncryptionStateAndInheritanceMatchNative(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
