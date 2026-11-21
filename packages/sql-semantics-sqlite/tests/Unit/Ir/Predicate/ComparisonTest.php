<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;

#[CoversClass(Comparison::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    public function testKeepsOrderedOperandsAndReferenceOccurrence(): void
    {
        $column = new ColumnReference(0, 1);
        $comparison = new Comparison(ComparisonOperator::Greater, $column, new IntegerLiteral('3'));

        self::assertSame($column, $comparison->left);
        self::assertSame(1, $column->source);
        self::assertInstanceOf(IntegerLiteral::class, $comparison->right);
        self::assertSame('3', $comparison->right->decimal);
    }
}
