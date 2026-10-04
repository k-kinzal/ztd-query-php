<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Into;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

#[CoversClass(IntoVariables::class)]
#[Medium]
final class IntoVariablesTest extends TestCase
{
    public function testRenderWritesTheTargets(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select a, b from t into @x, y');

        self::assertSame('SELECT a, b FROM t INTO @x, y', $operation->toString());
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(IntoVariables::class, $operation->statement->into);
        self::assertInstanceOf(UserVariable::class, $operation->statement->into->targets[0]);
        self::assertInstanceOf(ProgramVariable::class, $operation->statement->into->targets[1]);
    }

    public function testADifferentNumberOfColumnsIsReported(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1, 2 INTO @x');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(CountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertSame([2, 1], [$operation->facts->diagnostics[0]->expected, $operation->facts->diagnostics[0]->actual]);
    }

    public function testAnEmptyTargetListIsRejected(): void
    {
        $this->expectExceptionMessage('INTO names at least one variable.');

        new IntoVariables([]);
    }

    public function testAnotherScalarIsRejected(): void
    {
        $this->expectExceptionMessage('An INTO target is a user variable or a stored program variable.');

        new IntoVariables([new NumberLiteral('1')]);
    }
}
