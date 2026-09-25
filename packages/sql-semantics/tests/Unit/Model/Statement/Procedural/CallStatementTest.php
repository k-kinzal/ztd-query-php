<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\CallStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CallStatement::class)]
#[Medium]
final class CallStatementTest extends TestCase
{
    public function testWithOriginRetainsProcedureAndArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CALL app.refresh(1, 'x')");
        self::assertInstanceOf(CallStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->arguments, $copy->arguments);
        self::assertSame("CALL `app`.`refresh`(1, 'x')", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithProcedureCallsAnotherProcedureImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CALL refresh(1)');
        self::assertInstanceOf(CallStatement::class, $statement);
        $changed = $statement->withProcedure(new QualifiedName(['app', 'rebuild']));
        self::assertSame(['app', 'rebuild'], $changed->procedure->parts);
        self::assertSame('CALL `app`.`rebuild`(1)', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame(['refresh'], $statement->procedure->parts);
    }

    public function testWithArgumentsReplacesTheArgumentList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CALL refresh(1, 2)');
        self::assertInstanceOf(CallStatement::class, $statement);
        $changed = $statement->withArguments([]);
        self::assertSame([], $changed->arguments);
        self::assertSame('CALL `refresh`()', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertCount(2, $statement->arguments);
    }

    public function testRejectsAnEmptyProcedureName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CALL refresh');
        $this->expectException(InvalidStructure::class);
        new CallStatement($statement->origin, new QualifiedName(['']));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CALL refresh');
        $this->expectException(InvalidStructure::class);
        new CallStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), new QualifiedName(['refresh']));
    }
}
