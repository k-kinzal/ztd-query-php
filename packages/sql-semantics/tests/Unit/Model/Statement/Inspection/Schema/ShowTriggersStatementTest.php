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
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTriggersStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowTriggersStatement::class)]
#[Medium]
final class ShowTriggersStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TRIGGERS');
        self::assertInstanceOf(ShowTriggersStatement::class, $statement);
        self::assertNull($statement->database);
        self::assertNull($statement->filter);
        self::assertCount(11, $statement->resultColumns());
        self::assertContains('Event', array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW TRIGGERS', $statement->toString());
    }

    public function testWithDatabaseSelectsAnotherDatabaseImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TRIGGERS IN app LIKE 'a%'");
        self::assertInstanceOf(ShowTriggersStatement::class, $statement);
        self::assertSame('app', $statement->database);
        $changed = $statement->withDatabase('other');
        self::assertNotSame($statement, $changed);
        self::assertSame('other', $changed->database);
        self::assertSame('app', $statement->database);
        self::assertSame("SHOW TRIGGERS FROM `other` LIKE 'a%'", $changed->toString());
        self::assertSame("SHOW TRIGGERS LIKE 'a%'", $statement->withDatabase(null)->toString());
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW TRIGGERS IN app LIKE 'a%'");
        $conditioned = $binder->bind('SHOW TRIGGERS WHERE `Event` IS NOT NULL');
        self::assertInstanceOf(ShowTriggersStatement::class, $statement);
        self::assertInstanceOf(ShowTriggersStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame('SHOW TRIGGERS FROM `app` WHERE (`Event` IS NOT NULL)', $changed->toString());
        self::assertSame('SHOW TRIGGERS FROM `app`', $statement->withFilter(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TRIGGERS IN app LIKE 'a%'");
        self::assertInstanceOf(ShowTriggersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->database, $copy->database);
        self::assertSame($statement->filter, $copy->filter);
    }

    public function testRejectsAnEmptyDatabaseName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TRIGGERS');
        self::assertInstanceOf(ShowTriggersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowTriggersStatement($statement->origin, '');
    }
}
