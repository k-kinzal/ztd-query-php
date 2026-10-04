<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RowExpression::class)]
#[Medium]
final class RowExpressionTest extends TestCase
{
    public function testDeriveScalarGivesARowOfTheWrittenWidth(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT (a, b) = (1, 2), (a, a, 3) = (1, 2, 3) FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(Binary::class, $statement->columns[0]->expression);
        self::assertInstanceOf(Binary::class, $statement->columns[1]->expression);
        self::assertInstanceOf(RowExpression::class, $statement->columns[0]->expression->left);
        self::assertInstanceOf(RowExpression::class, $statement->columns[1]->expression->left);
        $row = $statement->columns[0]->expression->left;
        self::assertCount(2, $row->items);
        self::assertInstanceOf(ColumnUse::class, $row->items[0]);
        $fact = $operation->facts->scalar($row);
        self::assertEquals(new Known(new Vector(2)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertEquals(new Known(new Vector(3)), $operation->facts->scalar($statement->columns[1]->expression->left)->type);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($statement->columns[1]->expression->left)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarCombinesTheNullFactsOfTheElements(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, ?) = (1, 2), (NULL, 1) = (1, 2), (1, 2) = (3, 4)', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        $rows = array_map(static function (object $column): RowExpression {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(Binary::class, $column->expression);
            self::assertInstanceOf(RowExpression::class, $column->expression->left);

            return $column->expression->left;
        }, $statement->columns);
        self::assertSame(Nullability::Dependent, $operation->facts->scalar($rows[0])->nullability);
        self::assertSame(Nullability::Nullable, $operation->facts->scalar($rows[1])->nullability);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($rows[2])->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarAsAResultColumnIsReportedByTheSelection(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2)', []);

        self::assertEquals([new Misuse(MisuseRule::TooManyValueColumns)], $operation->facts->diagnostics);
        self::assertEquals(new Known(new Vector(2)), $operation->field(0)->type);
    }

    public function testRenderWritesTheElementsInParentheses(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select (a,b) = (1,2), (1, 2, 3) in (select 1, 2, 3) from t');

        self::assertSame('SELECT (a, b) = (1, 2), (1, 2, 3) IN (SELECT 1, 2, 3) FROM t', $operation->toString());
    }
}
