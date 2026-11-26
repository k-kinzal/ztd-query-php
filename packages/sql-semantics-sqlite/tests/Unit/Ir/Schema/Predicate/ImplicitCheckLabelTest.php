<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel;

#[CoversClass(ImplicitCheckLabel::class)]
#[Small]
final class ImplicitCheckLabelTest extends TestCase
{
    public function testOperationResolvesTheAlternateInequality(): void
    {
        $label = new ImplicitCheckLabel(new Identifier('a'), ComparisonSpelling::BangEqual, false);

        self::assertSame(ComparisonOperator::NotEqual, $label->operation());
    }

    public function testExpressionUsesTheCheckedLexicalChoices(): void
    {
        $label = new ImplicitCheckLabel(new Identifier('A'), ComparisonSpelling::DoubleEqual, false, "\t", '', 2);

        self::assertSame("A\t==001", $label->expression(new IntegerLiteral('1')));
    }
}
