<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\RoutineChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;

#[CoversClass(RoutineChecks::class)]
#[Medium]
final class RoutineChecksTest extends TestCase
{
    public function testOptionsReportsInvalidParallelModes(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER FUNCTION f() PARALLEL fast', []);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::ParallelLevel)], $operation->facts->diagnostics);
    }

    public function testBodyReportsBodyProblems(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertEquals(
            [[new RoutineProblem(RoutineProblemKind::MissingBody)], [new RoutineProblem(RoutineProblemKind::DuplicateBody)], [new RoutineProblem(RoutineProblemKind::InlineBodyLanguage)]],
            [
                $semantics->analyze('CREATE PROCEDURE p() LANGUAGE sql')->facts->diagnostics,
                $semantics->analyze("CREATE PROCEDURE p() AS 'x' BEGIN ATOMIC END")->facts->diagnostics,
                $semantics->analyze('CREATE PROCEDURE p() LANGUAGE plpgsql BEGIN ATOMIC END')->facts->diagnostics,
            ],
        );
    }

    public function testParametersReportsParameterProblems(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertEquals(
            [
                [new RoutineProblem(RoutineProblemKind::OutputInTableFunction)],
                [new RoutineProblem(RoutineProblemKind::DefaultOnOutput)],
                [new RoutineProblem(RoutineProblemKind::ProcedureOutputAfterDefault)],
                [],
            ],
            [
                $semantics->analyze("CREATE FUNCTION f(OUT a int4) RETURNS TABLE (b int4) LANGUAGE sql AS 'x'")->facts->diagnostics,
                $semantics->analyze("CREATE FUNCTION f(OUT a int4 DEFAULT 1) LANGUAGE sql AS 'x'")->facts->diagnostics,
                $semantics->analyze("CREATE PROCEDURE p(a int4 DEFAULT 1, OUT b int4) LANGUAGE sql AS 'x'")->facts->diagnostics,
                $semantics->analyze("CREATE FUNCTION f(VARIADIC a int4[], OUT b int4) LANGUAGE sql AS 'x'")->facts->diagnostics,
            ],
        );
    }

    public function testParameterReportsAnInputAfterVariadic(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze("CREATE FUNCTION f(VARIADIC a int4[], b int4) LANGUAGE sql AS 'x'");
        $statement = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame([null, RoutineProblemKind::VariadicNotLast], [(new RoutineChecks())->parameter($statement, $statement->parameters->parameters[1], false, false), (new RoutineChecks())->parameter($statement, $statement->parameters->parameters[1], true, false)]);
    }

    public function testNamesReportsSharedInputOrOutputNames(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertEquals(
            [[new RoutineProblem(RoutineProblemKind::DuplicateParameter, 'a')], [], [new RoutineProblem(RoutineProblemKind::OutputInTableFunction), new RoutineProblem(RoutineProblemKind::DuplicateParameter, 'a')]],
            [
                $semantics->analyze("CREATE FUNCTION f(a int4, INOUT a int4) LANGUAGE sql AS 'x'")->facts->diagnostics,
                $semantics->analyze("CREATE FUNCTION f(a int4, OUT a int4) LANGUAGE sql AS 'x'")->facts->diagnostics,
                $semantics->analyze("CREATE FUNCTION f(OUT a int4) RETURNS TABLE (a int4) LANGUAGE sql AS 'x'")->facts->diagnostics,
            ],
        );
    }
}
