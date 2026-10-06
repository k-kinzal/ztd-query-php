<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AlterRoutine::class)]
#[Medium]
final class AlterRoutineTest extends TestCase
{
    public function testDeriveStatementReportsAttributesOfProcedures(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER PROCEDURE p() IMMUTABLE');
        self::assertEquals([new RoutineProblem(RoutineProblemKind::ProcedureAttribute)], $operation->facts->diagnostics);
    }

    public function testRenderDropsTheIgnoredRestrict(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER ROUTINE r(int4) EXTERNAL SECURITY INVOKER NOT LEAKPROOF RESTRICT');
        self::assertSame('ALTER ROUTINE r (int4) SECURITY INVOKER NOT LEAKPROOF', $operation->toString());
    }

    public function testRejectsAnotherKind(): void
    {
        $this->expectExceptionMessage('ALTER names a function, a procedure or a routine.');
        new AlterRoutine(ObjectKind::Aggregate, new RoutineSignature(new DottedName([new Name('f')])), [RoutineAttribute::Stable]);
    }

    public function testRejectsAnOptionOfCreateOnly(): void
    {
        $this->expectExceptionMessage('ALTER changes only the options CREATE and ALTER share.');
        new AlterRoutine(ObjectKind::Function, new RoutineSignature(new DottedName([new Name('f')])), [RoutineAttribute::Window]);
    }
}
