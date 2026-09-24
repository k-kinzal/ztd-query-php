<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowRoutineCodeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowRoutineCodeStatement::class)]
#[Medium]
final class ShowRoutineCodeStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW FUNCTION CODE app.calc');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        self::assertSame(RoutineKind::Function, $statement->routine);
        self::assertSame(['app', 'calc'], $statement->name->parts);
        self::assertSame(['Pos', 'Instruction'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('bigint', $statement->resultColumns()[0]->expression->type->name);
        self::assertSame('SHOW FUNCTION CODE `app`.`calc`', $statement->toString());
    }

    public function testWithRoutineListsTheOtherKindImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW FUNCTION CODE calc');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        $changed = $statement->withRoutine(RoutineKind::Procedure);
        self::assertNotSame($statement, $changed);
        self::assertSame(RoutineKind::Function, $statement->routine);
        self::assertSame('SHOW PROCEDURE CODE `calc`', $changed->toString());
    }

    public function testWithNameNamesAnotherRoutineImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCEDURE CODE sync');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'other']));
        self::assertNotSame($statement, $changed);
        self::assertSame(['sync'], $statement->name->parts);
        self::assertSame('SHOW PROCEDURE CODE `app`.`other`', $changed->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCEDURE CODE sync');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->routine, $statement->name], [$copy->routine, $copy->name]);
    }

    public function testRejectsMoreThanADatabaseQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCEDURE CODE sync');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowRoutineCodeStatement($statement->origin, RoutineKind::Procedure, new QualifiedName(['a', 'b', 'c']));
    }
}
