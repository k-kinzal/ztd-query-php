<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextOperator;

#[CoversClass(TextComparison::class)]
#[Small]
final class TextComparisonTest extends TestCase
{
    public function testRetainsTheTypedOperandsInTheirWrittenOrder(): void
    {
        $literal = new TextLiteral('A ');
        $field = new TextColumnReference(1, 2);
        $comparison = new TextComparison(TextOperator::Less, $literal, $field);

        self::assertSame($literal, $comparison->left);
        self::assertSame($field, $comparison->right);
        self::assertSame(TextOperator::Less, $comparison->operator);
        self::assertSame(TextCollation::Utf8mb4Bin, $comparison->collation);
    }
}
