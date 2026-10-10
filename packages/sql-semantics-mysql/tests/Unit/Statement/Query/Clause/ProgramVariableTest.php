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

    public function testDeriveScalarTypesAVariableARunningProgramDeclares(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'), program: [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow(new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), [new \SqlSemantics\Statement\Identifier\Name('n')], [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer()])]);
        $operation = $semantics->analyze('SELECT 1 LIMIT n', $semantics->context(null, true, null, $settings));
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $limit = $select->limit;
        self::assertInstanceOf(RowLimit::class, $limit);

        self::assertSame([\SqlSemantics\Statement\Type\Known::class, []], [$operation->facts->scalar($limit->count)->type::class, $operation->facts->diagnostics]);
    }
}
