<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparisonLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextOperator;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(TextComparisonLowering::class)]
#[Medium]
final class TextComparisonLoweringTest extends TestCase
{
    public function testLowerResolvesTheRightCollationAndNotEqualAlias(): void
    {
        $analysis = (new Inspection())->inspect("SELECT 'A' != ('a' COLLATE pg_catalog.\"C\") AS found");
        self::assertInstanceOf(Select::class, $analysis->statement);
        $target = $analysis->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(BinaryOperation::class, $target->expression);
        $comparison = (new TextComparisonLowering())->lower($target->expression, new SourceBinding($analysis->binding(), null), true);
        self::assertNotNull($comparison);
        self::assertSame(TextOperator::NotEqual, $comparison->operator);
        self::assertSame(TextCollation::C, $comparison->collation);
        self::assertInstanceOf(TextLiteral::class, $comparison->left);
        self::assertSame('A', $comparison->left->value);
    }

    public function testOperandUsesTheCollatedArgumentsValue(): void
    {
        $analysis = (new Inspection())->inspect("SELECT 'a' COLLATE pg_catalog.\"C\"");
        self::assertInstanceOf(Select::class, $analysis->statement);
        $target = $analysis->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(Collation::class, $target->expression);

        $value = (new TextComparisonLowering())->operand($target->expression, new SourceBinding($analysis->binding(), null), true);
        self::assertInstanceOf(TextLiteral::class, $value);
        self::assertSame('a', $value->value);
    }

    public function testUngroupFindsAnExplicitCollation(): void
    {
        $analysis = (new Inspection())->inspect("SELECT ('a' COLLATE pg_catalog.\"C\")");
        self::assertInstanceOf(Select::class, $analysis->statement);
        $target = $analysis->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(Grouped::class, $target->expression);
        self::assertInstanceOf(Collation::class, (new TextComparisonLowering())->ungroup($target->expression));
    }
}
