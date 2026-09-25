<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogEventsStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowBinaryLogEventsStatement::class)]
#[Medium]
final class ShowBinaryLogEventsStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResultColumnsListTheEventFieldsAndOperandsSurviveRebindingAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SHOW BINLOG EVENTS IN 'binlog.0''1' FROM 0157 LIMIT 2, 5");
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertSame(["binlog.0'1", '0157'], [$statement->log, $statement->position?->text]);
        self::assertSame(['5', '2'], [$statement->limit?->count->spelling(), $statement->limit?->offset?->spelling()]);
        self::assertSame(['Log_name', 'Pos', 'Event_type', 'Server_id', 'End_log_pos', 'Info'], array_column($statement->resultColumns(), 'name'));
        self::assertSame("SHOW BINLOG EVENTS IN 'binlog.0''1' FROM 0157 LIMIT 5 OFFSET 2", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithLogReadsAnotherFileImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINLOG EVENTS');
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        $changed = $statement->withLog('b.2');
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->log);
        self::assertSame("SHOW BINLOG EVENTS IN 'b.2'", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithPositionStartsElsewhereImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW BINLOG EVENTS');
        $other = $binder->bind('SHOW BINLOG EVENTS FROM 4');
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $other);
        $changed = $statement->withPosition($other->position);
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->position);
        self::assertSame('SHOW BINLOG EVENTS FROM 4', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithLimitReplacesTheWindowImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW BINLOG EVENTS LIMIT 3');
        $other = $binder->bind('SHOW BINLOG EVENTS LIMIT ?');
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $other);
        $changed = $statement->withLimit($other->limit);
        self::assertNotSame($statement, $changed);
        self::assertSame('3', $statement->limit?->count->spelling());
        self::assertSame('SHOW BINLOG EVENTS LIMIT ?', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW BINLOG EVENTS IN 'x' FROM 4 LIMIT 1");
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->log, $statement->position, $statement->limit], [$copy->log, $copy->position, $copy->limit]);
    }

    public function testRejectsATextPosition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW BINLOG EVENTS');
        $pattern = $binder->bind("SHOW DATABASES LIKE 'x'");
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        self::assertInstanceOf(ShowDatabasesStatement::class, $pattern);
        self::assertInstanceOf(PatternFilter::class, $pattern->filter);
        $this->expectException(InvalidStructure::class);
        new ShowBinaryLogEventsStatement($statement->origin, null, $pattern->filter->pattern);
    }
}
