<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(Grouped::class)]
#[Medium]
final class GroupedTest extends TestCase
{
    public function testDeriveScalarKeepsTheTypeAndNullFactAndLeavesTheResolutionToTheOperand(): void
    {
        $semantics = new Semantics();
        $create = $semantics->inspect('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->inspect('SELECT (a), ((b)) FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(Grouped::class, $statement->columns[0]->expression);
        self::assertInstanceOf(Grouped::class, $statement->columns[1]->expression);
        self::assertInstanceOf(ColumnUse::class, $statement->columns[0]->expression->operand);
        self::assertInstanceOf(Grouped::class, $statement->columns[1]->expression->operand);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        $inner = $operation->facts->scalar($statement->columns[0]->expression->operand);
        self::assertInstanceOf(ResolvedColumn::class, $inner->resolution);
        self::assertNull($fact->resolution);
        self::assertSame($inner->type, $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame($create->declarations()[0]->columns[0], $operation->field(0)->column());
        self::assertSame($create->declarations()[0]->columns[1], $operation->field(1)->column());
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
    }

    public function testDeriveScalarKeepsANullOperandNull(): void
    {
        $semantics = new Semantics();
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new Grouped(new NullLiteral()))]));

        self::assertInstanceOf(NullOnly::class, $operation->field(0)->type);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertSame('SELECT (NULL)', $operation->toString());
    }

    public function testRenderKeepsEveryWrittenGrouping(): void
    {
        $operation = (new Semantics())->inspect('select (1 + 2) * 3, ((a)), (1) from t');

        self::assertSame('SELECT (1 + 2) * 3, ((a)), (1) FROM t', $operation->toString());
    }
}
