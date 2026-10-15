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
final class LegacyStorageInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $inputs = [
            'ALTER TABLESPACE _sqlfaker_identifier DROP DATAFILE \'text\'',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' ENGINE _sqlfaker_identifier',
            'ALTER TABLESPACE _sqlfaker_identifier CHANGE DATAFILE \'text\' AUTOEXTEND_SIZE 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\'',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' USE LOGFILE GROUP _sqlfaker_identifier',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' INITIAL_SIZE 18446744073709551615',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' INITIAL_SIZE 1K',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' INITIAL_SIZE 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' NODEGROUP 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' WAIT WAIT',
            'ALTER TABLESPACE _sqlfaker_identifier NOT ACCESSIBLE',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' AUTOEXTEND_SIZE 1',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' WAIT WAIT',
            'ALTER TABLESPACE _sqlfaker_identifier READ_ONLY',
            'ALTER TABLESPACE _sqlfaker_identifier CHANGE DATAFILE \'text\' INITIAL_SIZE 1 INITIAL_SIZE 1',
            'DROP TABLESPACE _sqlfaker_identifier WAIT WAIT',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' INITIAL_SIZE 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' MAX_SIZE 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' COMMENT \'text\'',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\'',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' MAX_SIZE 1',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' WAIT , WAIT',
            'ALTER TABLESPACE _sqlfaker_identifier CHANGE DATAFILE \'text\' INITIAL_SIZE 1',
            'ALTER TABLESPACE _sqlfaker_identifier CHANGE DATAFILE \'text\' INITIAL_SIZE 1 , INITIAL_SIZE 1',
            'DROP TABLESPACE _sqlfaker_identifier , WAIT',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' EXTENT_SIZE 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' WAIT',
            'ALTER TABLESPACE _sqlfaker_identifier READ_WRITE',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' INITIAL_SIZE 1',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' WAIT',
            'ALTER TABLESPACE _sqlfaker_identifier CHANGE DATAFILE \'text\' MAX_SIZE 1',
            'DROP TABLESPACE _sqlfaker_identifier ENGINE _sqlfaker_identifier',
            'ALTER TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' INITIAL_SIZE 2147483648',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' AUTOEXTEND_SIZE 1',
            'CREATE TABLESPACE _sqlfaker_identifier ADD DATAFILE \'text\' WAIT , WAIT',
            'DROP TABLESPACE _sqlfaker_identifier NO_WAIT',
        ];
        $statements = ["SET sql_mode='NO_ENGINE_SUBSTITUTION'", ...$inputs, "SET sql_mode=''", ...$inputs];
        foreach (['5.6.51'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_STORAGE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
            }
            yield $release => [$release, $definition, $statements];
        }
    }

    /**
     * @param list<string> $statements Formerly differing SQL Faker inputs with engine substitution enabled and disabled
     */
    #[DataProvider('providerInputs')]
    public function testLegacyStorageSeedRegressionsMatchNative(string $release, DisposableMySql $definition, array $statements): void
    {
        $container = Testcontainers::run($definition);
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
