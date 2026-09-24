<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Session\ProcedureCalls;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProcedureCalls::class)]
#[Medium]
final class ProcedureCallsTest extends TestCase
{
    #[TestWith(['CALL p()', 'CALL "p"()'])]
    #[TestWith(['CALL s.p(1, a => 2, VARIADIC b := ARRAY[1])', 'CALL "s"."p"(1, "a" => 2, VARIADIC "b" => ARRAY[1])'])]
    #[TestWith(['CALL p(ALL 1)', 'CALL "p"(1)'])]
    #[TestWith(['CALL p(VARIADIC ARRAY[1])', 'CALL "p"(VARIADIC ARRAY[1])'])]
    public function testBindReadsTheProcedureAndItsArguments(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['CALL p(DISTINCT 1)'])]
    #[TestWith(['CALL p(*)'])]
    #[TestWith(['CALL p(1 ORDER BY 1)'])]
    #[TestWith(['CALL p(a => 1, 2)'])]
    #[TestWith(['CALL p(a => 1, a := 2)'])]
    public function testBindRejectsCallsTheServerRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProcedureCall->message());
        $binder->bind($sql);
    }

    public function testBindRejectsAnImproperProcedureName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoutineName->message());
        $binder->bind('CALL a.b.c.d()');
    }
}
