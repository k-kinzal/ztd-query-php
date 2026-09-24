<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowTablesStatement::class)]
#[Medium]
final class ShowTablesStatementTest extends TestCase
{
    public function testResultColumnsLabelTheNameWithTheListedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, 'main'))->build()))->bind('SHOW TABLES');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        self::assertNull($statement->database);
        self::assertFalse($statement->full);
        self::assertFalse($statement->extended);
        self::assertSame(['Tables_in_main'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW TABLES', $statement->toString());
    }

    public function testWithFullAddsTheObjectTypeImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TABLES FROM app LIKE 'u%'");
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $changed = $statement->withFull(true);
        self::assertNotSame($statement, $changed);
        self::assertFalse($statement->full);
        self::assertTrue($changed->full);
        self::assertSame(['Tables_in_app', 'Table_type'], array_column($changed->resultColumns(), 'name'));
        self::assertSame("SHOW FULL TABLES FROM `app` LIKE 'u%'", $changed->toString());
    }

    public function testWithExtendedIncludesHiddenTablesImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW FULL TABLES');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $changed = $statement->withExtended(true);
        self::assertNotSame($statement, $changed);
        self::assertFalse($statement->extended);
        self::assertTrue($changed->extended);
        self::assertSame('SHOW EXTENDED FULL TABLES', $changed->toString());
        self::assertSame('SHOW TABLES', $changed->withExtended(false)->withFull(false)->toString());
    }

    public function testWithDatabaseSelectsAnotherDatabaseImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLES IN app');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $changed = $statement->withDatabase('other');
        self::assertNotSame($statement, $changed);
        self::assertSame('app', $statement->database);
        self::assertSame('other', $changed->database);
        self::assertSame(['Tables_in_other'], array_column($changed->resultColumns(), 'name'));
        self::assertSame('SHOW TABLES', $statement->withDatabase(null)->toString());
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW TABLES FROM app LIKE 'u%'");
        $conditioned = $binder->bind("SHOW FULL TABLES FROM app WHERE Table_type = 'VIEW'");
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        self::assertInstanceOf(ShowTablesStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFull(true)->withFilter($conditioned->filter);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame("SHOW FULL TABLES FROM `app` WHERE (`Table_type` = 'VIEW')", $changed->toString());
        self::assertSame('SHOW TABLES FROM `app`', $statement->withFilter(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW EXTENDED FULL TABLES FROM app LIKE 'u%'");
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->database, $statement->filter, $statement->full, $statement->extended], [$copy->database, $copy->filter, $copy->full, $copy->extended]);
    }

    public function testRejectsAnEmptyDatabaseName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLES');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowTablesStatement($statement->origin, '');
    }

    public function testRejectsExtendedOnALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SHOW TABLES');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowTablesStatement($statement->origin, null, null, false, true);
    }
}
