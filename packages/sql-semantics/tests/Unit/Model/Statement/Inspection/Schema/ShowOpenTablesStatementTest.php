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
use SqlSemantics\Model\Statement\Inspection\Schema\ShowOpenTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowOpenTablesStatement::class)]
#[Medium]
final class ShowOpenTablesStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW OPEN TABLES');
        self::assertInstanceOf(ShowOpenTablesStatement::class, $statement);
        self::assertNull($statement->database);
        self::assertNull($statement->filter);
        self::assertCount(4, $statement->resultColumns());
        self::assertContains('In_use', array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW OPEN TABLES', $statement->toString());
    }

    public function testWithDatabaseSelectsAnotherDatabaseImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW OPEN TABLES IN app LIKE 'a%'");
        self::assertInstanceOf(ShowOpenTablesStatement::class, $statement);
        self::assertSame('app', $statement->database);
        $changed = $statement->withDatabase('other');
        self::assertNotSame($statement, $changed);
        self::assertSame('other', $changed->database);
        self::assertSame('app', $statement->database);
        self::assertSame("SHOW OPEN TABLES FROM `other` LIKE 'a%'", $changed->toString());
        self::assertSame("SHOW OPEN TABLES LIKE 'a%'", $statement->withDatabase(null)->toString());
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW OPEN TABLES IN app LIKE 'a%'");
        $conditioned = $binder->bind('SHOW OPEN TABLES WHERE `In_use` IS NOT NULL');
        self::assertInstanceOf(ShowOpenTablesStatement::class, $statement);
        self::assertInstanceOf(ShowOpenTablesStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame('SHOW OPEN TABLES FROM `app` WHERE (`In_use` IS NOT NULL)', $changed->toString());
        self::assertSame('SHOW OPEN TABLES FROM `app`', $statement->withFilter(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW OPEN TABLES IN app LIKE 'a%'");
        self::assertInstanceOf(ShowOpenTablesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->database, $copy->database);
        self::assertSame($statement->filter, $copy->filter);
    }

    public function testRejectsAnEmptyDatabaseName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW OPEN TABLES');
        self::assertInstanceOf(ShowOpenTablesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowOpenTablesStatement($statement->origin, '');
    }
}
