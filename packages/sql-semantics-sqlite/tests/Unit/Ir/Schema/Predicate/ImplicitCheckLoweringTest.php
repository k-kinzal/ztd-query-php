<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLowering;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Spelled;

#[CoversClass(ImplicitCheckLowering::class)]
#[Small]
final class ImplicitCheckLoweringTest extends TestCase
{
    public function testLabelMatchesTheUnsignedDecimalPresentation(): void
    {
        $condition = new Comparison(ComparisonOperator::Greater, new ColumnReference(0), new IntegerLiteral('0'));
        $layout = new Layout([new Spelled('', 'A'), new Spelled('', '>'), new Spelled(' ', '000')]);

        self::assertSame(2, (new ImplicitCheckLowering())->label($condition, $layout)->leadingZeroes);
    }

    public function testPresentationDecodesTheQuotedIdentifier(): void
    {
        $label = (new ImplicitCheckLowering())->presentation('[a b]', ComparisonSpelling::Equal, '', '', 0);

        self::assertTrue($label->quoted);
        self::assertSame('a b', $label->columnName->value);
    }
}
