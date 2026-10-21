<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class BinlogPartialHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    /**
     * @throws JsonException When the retained native fixture is malformed
     */
    #[DataProvider('providerReleases')]
    public function testFullAndMinimalImagesPreserveTypedJsonAcrossPartialChanges(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = (new Instance($release))->connect();
            $fixture = file_get_contents(__DIR__ . '/fixtures/binlog-partial.json');
            self::assertIsString($fixture);
            $events = json_decode($fixture, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($events);
            self::assertIsString($events[0]);
            self::assertIsString($events[1]);
            self::assertIsString($events[2]);
            self::assertIsString($events[3]);
            self::assertIsString($events[4]);
            self::assertIsString($events[5]);
            $eventNodes = array_map(static function (string $sql): BinlogEvent {
                return new BinlogEvent(new Text(substr($sql, 8, -1)));
            }, [$events[0], $events[1], $events[2], $events[3], $events[4], $events[5]]);
            $definition = file_get_contents(__DIR__ . '/fixtures/binlog-partial.sql');
            self::assertIsString($definition);
            $history = [
                'CREATE DATABASE binlog_fixture', $definition, $eventNodes[0], $eventNodes[1],
                "SELECT *,JSON_TYPE(k->'$.d'),JSON_TYPE(k->'$.t'),JSON_TYPE(k->'$.b') FROM binlog_fixture.rows_test ORDER BY id",
                $eventNodes[2], 'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
                $eventNodes[3], 'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
                $eventNodes[4], 'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
                $eventNodes[5], 'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
                'DROP TABLE binlog_fixture.rows_test', $definition,
                ...$eventNodes, 'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
            ];
            $sql = array_map(static fn (BinlogEvent|string $node): string => is_string($node) ? $node : "BINLOG '" . $node->events->value . "'", $history);
            $expected = array_map(static fn (string $statement): string => serialize((new Native())->observe($native, $statement)), $sql);
            $actual = array_map(static function (BinlogEvent|string $node) use ($memory): string {
                $statement = is_string($node) ? $memory->analyze($node)->statement : $node;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
                return serialize((new Memory())->observe($memory, $statement));
            }, $history);

            self::assertSame($expected, $actual, $release);
            $table = $memory->instance->dictionary->table('binlog_fixture', 'rows_test');
            self::assertNotNull($table);
            self::assertCount(1, $table->data->rows);
            self::assertSame([1, null, '{"q": 1}', 3], array_values($table->data->rows)[0]);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
