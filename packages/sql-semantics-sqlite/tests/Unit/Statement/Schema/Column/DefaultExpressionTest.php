<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefaultNotConstant;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(DefaultExpression::class)]
#[Medium]
final class DefaultExpressionTest extends TestCase
{
    public function testDeriveConstraintSeesNoColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT, b DEFAULT (a + 1))', []);

        self::assertCount(2, $operation->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $operation->diagnostics()[0]);
        self::assertInstanceOf(DefaultNotConstant::class, $operation->diagnostics()[1]);
        self::assertSame('b', $operation->diagnostics()[1]->column->value);
    }

    public function testDeriveConstraintAcceptsAConstantExpression(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DEFAULT (1 + 2))', []);
        $statement = $operation;

        self::assertInstanceOf(CreateTable::class, $statement);
        $default = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(DefaultExpression::class, $default);
        self::assertTrue($default->expression->hasBinding());
        self::assertSame([], $operation->diagnostics());
    }

    public function testRenderWritesTheExpressionInParentheses(): void
    {
        self::assertSame('CREATE TABLE t (a DEFAULT (1 + 2))', (new Semantics(Dialect::Sqlite))->analyze('create table t (a default(1+2))')->toString());
    }
}
