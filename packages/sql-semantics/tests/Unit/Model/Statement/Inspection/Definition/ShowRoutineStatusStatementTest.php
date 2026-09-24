<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowRoutineStatusStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowRoutineStatusStatement::class)]
#[Medium]
final class ShowRoutineStatusStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCEDURE STATUS');
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $statement);
        self::assertSame(RoutineKind::Procedure, $statement->routine);
        self::assertNull($statement->filter);
        self::assertSame(['Db', 'Name', 'Type', 'Definer', 'Modified', 'Created', 'Security_type', 'Comment', 'character_set_client', 'collation_connection', 'Database Collation'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('datetime', $statement->resultColumns()[4]->expression->type->name);
    }

    public function testWithRoutineListsTheOtherKindImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW FUNCTION STATUS LIKE 'calc%'");
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $statement);
        $changed = $statement->withRoutine(RoutineKind::Procedure);
        self::assertNotSame($statement, $changed);
        self::assertSame(RoutineKind::Function, $statement->routine);
        self::assertSame("SHOW PROCEDURE STATUS LIKE 'calc%'", $changed->toString());
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW FUNCTION STATUS LIKE 'calc%'");
        $conditioned = $binder->bind("SHOW FUNCTION STATUS WHERE Db = 'app'");
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $statement);
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame("SHOW FUNCTION STATUS WHERE (`Db` = 'app')", $changed->toString());
        self::assertSame('SHOW FUNCTION STATUS', $statement->withFilter(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW FUNCTION STATUS LIKE 'calc%'");
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->routine, $statement->filter], [$copy->routine, $copy->filter]);
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowRoutineStatusStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), RoutineKind::Function);
    }
}
