<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(ProgramVariable::class)]
#[Medium]
final class ProgramVariableTest extends TestCase
{
    public function testDeriveScalarDependsOnTheStoredProgram(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t LIMIT n');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(RowLimit::class, $operation->statement->limit);
        $type = $operation->facts->scalar($operation->statement->limit->count)->type;
        self::assertInstanceOf(Dependent::class, $type);
        self::assertSame('the session state: stored program variable n', $type->missing[0]->describe());
    }

    public function testRenderWritesTheName(): void
    {
        self::assertSame('SELECT a FROM t INTO x, `select`', (new Semantics(Dialect::MySql))->analyze("SELECT a FROM t INTO x, 'select'")->toString());
    }
}
