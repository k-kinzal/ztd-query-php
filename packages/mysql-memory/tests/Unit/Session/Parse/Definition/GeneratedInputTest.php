<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\GeneratedInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedInput::class)]
#[Small]
final class GeneratedInputTest extends TestCase
{
    public function testErrorPreservesTheVersionedFunctionDiagnostic(): void
    {
        $problem = new RefusedOperand(new Name('g'), OperandKind::FunctionCall, 'user');
        self::assertSame(3763, (new GeneratedInput())->error($problem, (new Instance())->connect())->getCode());
        self::assertSame(3102, (new GeneratedInput())->error($problem, (new Instance('5.7.44'))->connect())->getCode());
    }
    public function testErrorNamesTheRefusedOperandOfAnExpressionDefault(): void
    {
        $session = (new Instance())->connect();
        $role = \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Default;
        self::assertSame([3770, 3769, 3771, 3772], [
            (new GeneratedInput())->error(new RefusedOperand(new Name('a'), OperandKind::StoredFunction, '`f1`', role: $role), $session)->getCode(),
            (new GeneratedInput())->error(new RefusedOperand(new Name('a'), OperandKind::Subquery, role: $role), $session)->getCode(),
            (new GeneratedInput())->error(new RefusedOperand(new Name('a'), OperandKind::Row, role: $role), $session)->getCode(),
            (new GeneratedInput())->error(new RefusedOperand(new Name('a'), OperandKind::Variable, role: $role), $session)->getCode(),
        ]);
    }

    public function testErrorComesBeforeTheColumnsAreResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(3763);
        $this->expectExceptionMessage("Expression of generated column 'c' contains a disallowed function: rand.");

        $session->query('CREATE TABLE t1 (a INT, b INT AS (x), c INT AS (rand()))');
    }

    public function testErrorResolvesRetainedContextualDependenciesBeforeTheGenerationRole(): void
    {
        $expression = new \SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch(
            [new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('a'))],
            new \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable(new Name('missing')),
        );
        $problem = new RefusedOperand(new Name('g'), OperandKind::FunctionCall, 'match', expression: $expression);
        self::assertSame(1193, (new GeneratedInput())->error($problem, (new Instance())->connect())->getCode());
        self::assertSame($expression, $problem->expression);
    }
}
