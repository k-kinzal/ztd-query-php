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
final class DatabaseEncryptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE encryption_values', 'USE encryption_values'];
        foreach ([str_repeat('a', 127), str_repeat('b', 128), str_repeat('c', 129), str_repeat('é', 65), str_repeat('é', 63) . '€', "a\0b"] as $value) {
            $literal = "'" . str_replace("\0", '\\0', $value) . "'";
            $statements[] = 'ALTER DATABASE DEFAULT ENCRYPTION ' . $literal;
            $statements[] = 'CREATE DATABASE refused ENCRYPTION = ' . $literal;
        }
        foreach (['Y', 'N', 'y', 'n', 'yes', 'no', ' Y', 'Y ', "N\0", "n\0ignored", "Y\0bad", '', "\nY", 'Ｙ', 'ñ'] as $value) {
            $literal = "'" . str_replace("\0", '\\0', $value) . "'";
            $statements[] = 'ALTER DATABASE encryption_values ENCRYPTION ' . $literal;
            $statements[] = 'SHOW CREATE DATABASE encryption_values';
            $statements[] = "SELECT DEFAULT_ENCRYPTION FROM information_schema.schemata WHERE schema_name='encryption_values'";
            $statements[] = 'CREATE DATABASE encryption_created ENCRYPTION ' . $literal;
            $statements[] = 'SHOW CREATE DATABASE encryption_created';
            $statements[] = 'DROP DATABASE IF EXISTS encryption_created';
        }
        array_push(
            $statements,
            "ALTER DATABASE encryption_values READ ONLY 2 DEFAULT ENCRYPTION 't'",
            "ALTER DATABASE encryption_values READ ONLY 2 CHARACTER SET nope ENCRYPTION 't'",
            "ALTER DATABASE encryption_values CHARACTER SET nope READ ONLY 2 ENCRYPTION 't'",
            "ALTER DATABASE encryption_values ENCRYPTION 'bad' +",
            "ALTER DATABASE encryption_values ENCRYPTION 'bad' CHARACTER SET nope",
            "ALTER DATABASE encryption_values CHARACTER SET nope ENCRYPTION 'bad'",
            "ALTER DATABASE encryption_values CHARACTER SET utf8 ENCRYPTION 'bad'",
            "ALTER DATABASE encryption_values ENCRYPTION 'bad' CHARACTER SET utf8",
            "ALTER DATABASE encryption_values CHARACTER SET latin1 CHARACTER SET utf8mb4 ENCRYPTION 'bad'",
            "ALTER DATABASE `` ENCRYPTION 'bad'",
            "CREATE DATABASE encryption_values ENCRYPTION 'bad'",
            "CREATE DATABASE `` ENCRYPTION 'bad'",
            "CREATE DATABASE missing ENCRYPTION 'bad' ENCRYPTION 'N'",
            "SHOW PARSE_TREE CREATE DATABASE missing ENCRYPTION 'bad'",
            "SHOW PARSE_TREE ALTER DATABASE encryption_values ENCRYPTION 'bad'",
            "CREATE PROCEDURE encryption_values.p() BEGIN DECLARE x INT; DECLARE x INT; ALTER DATABASE encryption_values ENCRYPTION 'bad'; END",
            "CREATE PROCEDURE encryption_values.p() BEGIN SELECT DATE 'bad'; ALTER DATABASE encryption_values ENCRYPTION 'bad'; END",
        );
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The rejected encryption options to compare
     */
    #[DataProvider('providerReleases')]
    public function testEncryptionDiagnosticsMatchNative(string $release, array $statements): void
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
