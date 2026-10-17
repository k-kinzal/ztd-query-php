<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use JsonException;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ArrayIndexRetiredRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $statements = ['CREATE DATABASE placement', 'USE placement', 'CREATE TABLE t(id INT PRIMARY KEY,j JSON,UNIQUE KEY k((CAST(j AS DECIMAL(6,2) ARRAY))))'];
        $values = ['NULL', "'null'", "'[]'", "'[1,2,1]'", "'[null]'", "'1'", "'1.5'", "'true'", "'{}'", "'[[1]]'", "'[true,false]'", "'[\"1\",\"2\"]'", "'[\"bad\"]'", "'[\"abcdef\"]'", "'[-1,18446744073709551615]'", "'[1.234,2.345]'", "'[\"2024-01-01\"]'", "'[\"12:34:56\"]'", "'[\"2024-01-01 12:34:56\"]'"];
        foreach ($values as $value) {
            array_push($statements, 'INSERT INTO t VALUES(1,' . $value . ')', 'SELECT * FROM t', 'DELETE FROM t');
        }
        $statements[] = "INSERT INTO t VALUES(1,'[3,2,1]')";
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     * @throws JsonException When an observation cannot be serialized
     */
    #[DataProvider('providerHistories')]
    public function testRetiredEntryNotesHaveNoMemoryCounterpart(string $release, array $statements): void
    {
        $native = array_map(static function () use ($release, $statements): array {
            $container = Testcontainers::run(new DisposableMySql($release));
            try {
                $endpoint = $container->getData(Endpoint::class);
                $db = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                return array_map(static fn (string $sql): string => json_encode((new Observer())->observe($db, $sql, true), JSON_THROW_ON_ERROR), $statements);
            } finally {
                $container->stop();
            }
        }, [0, 1]);
        $server = Server::start($release);
        try {
            $db = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $memory = array_map(static fn (string $sql): string => json_encode((new Observer())->observe($db, $sql, true), JSON_THROW_ON_ERROR), $statements);
            $last = array_pop($memory);
            self::assertNotNull($last);
            $current = json_decode($last, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($current);
            self::assertSame([], $current['warnings']);
            $memory[] = $last;
            $normalized = array_map(static function (array $history): array {
                $last = array_pop($history);
                self::assertNotNull($last);
                $current = json_decode($last, true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($current);
                $note = ['Note', 3751, "Data truncated for functional index 'k' at row 1"];
                self::assertContains($current['warnings'], [[], [$note, $note, $note, $note]]);
                $current['warnings'] = [];
                $history[] = json_encode($current, JSON_THROW_ON_ERROR);

                return $history;
            }, $native);
            self::assertSame($memory, $normalized[0]);
            self::assertSame($memory, $normalized[1]);
        } finally {
            $server->stop();
        }
    }
}
