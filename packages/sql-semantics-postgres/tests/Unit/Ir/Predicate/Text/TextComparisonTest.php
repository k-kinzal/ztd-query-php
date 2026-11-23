<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextOperator;

#[CoversClass(TextComparison::class)]
#[Small]
final class TextComparisonTest extends TestCase
{
    public function testKeepsOrderedTypedOperands(): void
    {
        $field = new TextColumnReference(2, 1);
        $null = new NullLiteral();
        $test = new TextComparison(TextOperator::Greater, $field, $null, TextCollation::C);

        self::assertSame($field, $test->left);
        self::assertSame($null, $test->right);
        self::assertSame(TextOperator::Greater, $test->operator);
        self::assertSame(TextCollation::C, $test->collation);
    }
}
