<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArgument;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CallProcedureStatement::class)]
#[Medium]
final class CallProcedureStatementTest extends TestCase
{
    public function testWithOriginRetainsTheCall(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL s.p(1)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame(['s', 'p'], $copy->procedure->parts);
        self::assertCount(1, $copy->arguments);
        self::assertSame(StatementKind::Call, $copy->kind);
    }

    public function testWithProcedureCallsAnotherProcedure(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        self::assertSame('CALL "q"."r"(1)', $statement->withProcedure(new QualifiedName(['q', 'r']))->toString());
        self::assertSame(['p'], $statement->procedure->parts);
    }

    public function testWithArgumentsReplacesTheArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        self::assertSame('CALL "p"("x" => 1)', $statement->withArguments([new ProcedureArgument($statement->arguments[0]->value, 'x')])->toString());
        self::assertSame('CALL "p"()', $statement->withArguments([])->toString());
    }

    public function testRejectsFourNameComponents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p()');
        $this->expectException(InvalidStructure::class);
        new CallProcedureStatement($statement->origin, new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p()');
        $this->expectException(InvalidStructure::class);
        new CallProcedureStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), new QualifiedName(['p']));
    }
}
