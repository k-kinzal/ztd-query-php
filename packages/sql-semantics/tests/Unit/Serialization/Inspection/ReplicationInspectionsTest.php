<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogEventsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\ReplicationInspections;

#[CoversClass(ReplicationInspections::class)]
#[Medium]
final class ReplicationInspectionsTest extends TestCase
{
    #[TestWith(['mysql-8.0.44', 'SHOW MASTER LOGS', 'SHOW BINARY LOGS'])]
    #[TestWith(['mysql-8.3.0', 'SHOW BINARY LOG STATUS', 'SHOW MASTER STATUS'])]
    #[TestWith(['mysql-9.0.1', 'SHOW BINARY LOG STATUS', 'SHOW BINARY LOG STATUS'])]
    #[TestWith(['mysql-8.0.44', "SHOW SLAVE STATUS FOR CHANNEL 'c'", "SHOW SLAVE STATUS FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.0.44', 'SHOW SLAVE HOSTS', 'SHOW SLAVE HOSTS'])]
    #[TestWith(['mysql-8.4.7', 'SHOW RELAYLOG EVENTS LIMIT 1 FOR CHANNEL ""', "SHOW RELAYLOG EVENTS LIMIT 1 FOR CHANNEL ''"])]
    public function testWriteKeepsTheVocabularyAndReleaseSpelling(string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertSame($expected, ReplicationInspections::write($statement)?->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(ReplicationInspections::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW WARNINGS')));
    }

    public function testEventsWritesTheFileAndPositionWhenGiven(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINLOG EVENTS IN "f" FROM 7');
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertSame(['IN', "'f'", 'FROM', '7'], array_map(static fn ($tree): string => $tree->toString(), ReplicationInspections::events($statement->log, $statement->position)));
        self::assertSame([], ReplicationInspections::events(null, null));
    }

    public function testChannelWritesAStringOrNothing(): void
    {
        self::assertSame(['FOR CHANNEL', "'x'"], array_map(static fn ($tree): string => $tree->toString(), ReplicationInspections::channel('x')));
        self::assertSame([], ReplicationInspections::channel(null));
    }

    public function testTextQuotesTheName(): void
    {
        self::assertSame("'a''b'", ReplicationInspections::text("a'b")->toString());
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteSpellsEveryEventListing')]
    public function testWriteSpellsEveryEventListing(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, ReplicationInspections::write($statement)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteSpellsEveryEventListing(): iterable
    {
        return [
            'SHOW BINLOG EVENTS (MySql)' => [Dialect::MySql, null, [], 'SHOW BINLOG EVENTS', 'SHOW BINLOG EVENTS'],
            'SHOW BINLOG EVENTS IN \'log.1\' FROM 4 LIMIT 2, 5 (MySql)' => [Dialect::MySql, null, [], 'SHOW BINLOG EVENTS IN \'log.1\' FROM 4 LIMIT 2, 5', 'SHOW BINLOG EVENTS IN \'log.1\' FROM 4 LIMIT 5 OFFSET 2'],
            'SHOW BINLOG EVENTS IN \'log.1\' LIMIT 5 (MySql)' => [Dialect::MySql, null, [], 'SHOW BINLOG EVENTS IN \'log.1\' LIMIT 5', 'SHOW BINLOG EVENTS IN \'log.1\' LIMIT 5'],
            'SHOW RELAYLOG EVENTS IN \'r.1\' FROM 4 LIMIT 1 (MySql)' => [Dialect::MySql, null, [], 'SHOW RELAYLOG EVENTS IN \'r.1\' FROM 4 LIMIT 1', 'SHOW RELAYLOG EVENTS IN \'r.1\' FROM 4 LIMIT 1'],
        ];
    }
}
