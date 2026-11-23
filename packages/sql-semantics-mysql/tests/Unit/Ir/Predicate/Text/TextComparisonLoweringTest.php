<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparisonLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextOperator;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TextComparisonLowering::class)]
#[Medium]
final class TextComparisonLoweringTest extends TestCase
{
    public function testLowerKeepsTheFieldOnTheRightAndNormalizesTheOperator(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin)');
        $query = $inspection->inspect("SELECT 'A' != label AS value FROM app.t", [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        self::assertInstanceOf(Comparison::class, $item->expression);
        $binding = new SourceBinding($query->binding(), $query->statement->from);

        $model = (new TextComparisonLowering())->lower($item->expression, $binding);
        self::assertNotNull($model);
        self::assertInstanceOf(TextColumnReference::class, $model->right);
        self::assertSame(TextOperator::NotEqual, $model->operator);
    }

    public function testOperandKeepsDecodedText(): void
    {
        $query = (new Inspection())->inspect("SELECT 'a''b '");
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $binding = new SourceBinding($query->binding(), null);

        $value = (new TextComparisonLowering())->operand($item->expression, $binding);
        self::assertInstanceOf(TextLiteral::class, $value);
        self::assertSame("a'b ", $value->value);
    }
}
