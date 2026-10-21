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
final class BinlogDestinationHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDestinations(): iterable
    {
        $definitions = [
            'primary' => 'id INT PRIMARY KEY,value VARCHAR(20)',
            'unique' => 'id INT UNIQUE,value VARCHAR(20)',
            'none' => 'id INT,value VARCHAR(20)',
            'extra' => 'id INT PRIMARY KEY,value VARCHAR(20),extra INT NOT NULL DEFAULT 7',
            'fewer' => 'id INT PRIMARY KEY',
            'generated' => 'id INT PRIMARY KEY,value VARCHAR(20),g VARCHAR(40) AS(CONCAT(value,value))',
            'check' => "id INT PRIMARY KEY,value VARCHAR(20),CONSTRAINT chk CHECK(value <> 'first')",
        ];
        foreach (['8.4.7', '9.1.0'] as $release) {
            foreach ($definitions as $name => $columns) {
                yield $release . '/' . $name => [$release, $columns];
            }
        }
    }

    /**
     * @throws JsonException When the retained native fixture is malformed
     */
    #[DataProvider('providerDestinations')]
    public function testTheSameRowImagesRespectDestinationKeysColumnsAndChecks(string $release, string $columns): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = (new Instance($release))->connect();
            $fixture = file_get_contents(__DIR__ . '/fixtures/binlog-replay.json');
            self::assertIsString($fixture);
            $events = json_decode($fixture, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($events);
            self::assertIsString($events[0]);
            self::assertIsString($events[1]);
            self::assertIsString($events[2]);
            self::assertIsString($events[3]);
            $eventNodes = array_map(static function (string $sql): BinlogEvent {
                return new BinlogEvent(new Text(substr($sql, 8, -1)));
            }, [$events[0], $events[1], $events[2], $events[3]]);
            $history = [
                'CREATE DATABASE binlog_fixture',
                'CREATE TABLE binlog_fixture.rows_test (' . $columns . ')', $eventNodes[0], $eventNodes[1],
                'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
                "UPDATE binlog_fixture.rows_test SET value='manual' WHERE id=1", $eventNodes[2],
                'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
                'DROP TABLE binlog_fixture.rows_test',
                'CREATE TABLE binlog_fixture.rows_test (id INT PRIMARY KEY,value VARCHAR(20))', $eventNodes[1],
                'SELECT * FROM binlog_fixture.rows_test ORDER BY id',
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
            self::assertSame([[1, 'first'], [2, 'second']], array_values($table->data->rows));
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
