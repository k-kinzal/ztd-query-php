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
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\NonConstantDefault;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(DefaultExpression::class)]
#[Medium]
final class DefaultExpressionTest extends TestCase
{
    public function testDeriveConstraintSeesNoColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT, b DEFAULT (a + 1))', []);

        self::assertCount(2, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(NonConstantDefault::class, $operation->facts->diagnostics[1]);
        self::assertSame('b', $operation->facts->diagnostics[1]->column->value);
    }

    public function testDeriveConstraintAcceptsAConstantExpression(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DEFAULT (1 + 2))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $default = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(DefaultExpression::class, $default);
        self::assertTrue($operation->facts->covers($default->expression));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheExpressionInParentheses(): void
    {
        self::assertSame('CREATE TABLE t (a DEFAULT (1 + 2))', (new Semantics(Dialect::Sqlite))->analyze('create table t (a default(1+2))')->toString());
    }
}
