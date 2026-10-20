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
final class AlterDirectoryInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = [
            "ALTER TABLE t INDEX DIRECTORY 'text'", "ALTER TABLE absent.t INDEX DIRECTORY 'text'",
            'CREATE DATABASE d', 'USE d',
            "ALTER TABLE _sfHc4iYueR2SQJuJFdhp8HJfR1ggoK04aW3ePRELbaz1L8BatIQUy6Uh INDEX DIRECTORY 'text'",
            "ALTER TABLE missing INDEX DIRECTORY 'text'", "ALTER TABLE missing DATA DIRECTORY 'text'",
            "ALTER TABLE missing INDEX DIRECTORY 'one', INDEX DIRECTORY 'two'",
            "ALTER TABLE missing INDEX DIRECTORY 'i' DATA DIRECTORY 'd'",
            "ALTER TABLE missing DATA DIRECTORY 'd' INDEX DIRECTORY 'i'",
            "ALTER TABLE missing INDEX DIRECTORY 'text', ADD a DECIMAL(100)",
            "ALTER TABLE missing INDEX DIRECTORY 'text' bad_syntax",
            "PREPARE s FROM 'ALTER TABLE missing INDEX DIRECTORY \'text\''", 'EXECUTE s',
            "CREATE PROCEDURE p() ALTER TABLE missing INDEX DIRECTORY 'text'", 'CALL p()',
            'CREATE TABLE missing(a INT)', "ALTER TABLE missing INDEX DIRECTORY 'text'", "ALTER TABLE missing DATA DIRECTORY 'text'",
            "ALTER TABLE missing INDEX DIRECTORY 'i' DATA DIRECTORY 'd'", 'EXECUTE s', 'CALL p()',
            "ALTER TABLE missing INDEX DIRECTORY 'text', ENGINE=InnoDB", 'DROP DATABASE d',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Directory options before and after catalog setup, through direct, prepared and stored requests
     */
    #[DataProvider('providerStatements')]
    public function testDirectoryOptionsWarnOnlyAtExecutionInNativeOrder(string $release, array $statements): void
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
