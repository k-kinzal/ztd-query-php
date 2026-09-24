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
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTableStatusStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowTableStatusStatement::class)]
#[Medium]
final class ShowTableStatusStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLE STATUS');
        self::assertInstanceOf(ShowTableStatusStatement::class, $statement);
        self::assertNull($statement->database);
        self::assertNull($statement->filter);
        self::assertCount(18, $statement->resultColumns());
        self::assertContains('Engine', array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW TABLE STATUS', $statement->toString());
    }

    public function testWithDatabaseSelectsAnotherDatabaseImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TABLE STATUS IN app LIKE 'a%'");
        self::assertInstanceOf(ShowTableStatusStatement::class, $statement);
        self::assertSame('app', $statement->database);
        $changed = $statement->withDatabase('other');
        self::assertNotSame($statement, $changed);
        self::assertSame('other', $changed->database);
        self::assertSame('app', $statement->database);
        self::assertSame("SHOW TABLE STATUS FROM `other` LIKE 'a%'", $changed->toString());
        self::assertSame("SHOW TABLE STATUS LIKE 'a%'", $statement->withDatabase(null)->toString());
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW TABLE STATUS IN app LIKE 'a%'");
        $conditioned = $binder->bind('SHOW TABLE STATUS WHERE `Engine` IS NOT NULL');
        self::assertInstanceOf(ShowTableStatusStatement::class, $statement);
        self::assertInstanceOf(ShowTableStatusStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame('SHOW TABLE STATUS FROM `app` WHERE (`Engine` IS NOT NULL)', $changed->toString());
        self::assertSame('SHOW TABLE STATUS FROM `app`', $statement->withFilter(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TABLE STATUS IN app LIKE 'a%'");
        self::assertInstanceOf(ShowTableStatusStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->database, $copy->database);
        self::assertSame($statement->filter, $copy->filter);
    }

    public function testRejectsAnEmptyDatabaseName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLE STATUS');
        self::assertInstanceOf(ShowTableStatusStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowTableStatusStatement($statement->origin, '');
    }
}
