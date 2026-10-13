<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ItemLayouts;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(ItemLayouts::class)]
#[Small]
final class ItemLayoutsTest extends TestCase
{
    public function testCheckAcceptsAnAlignedLayout(): void
    {
        $layout = new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2')]);
        $item = new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: $layout);

        self::assertSame($layout, $item->layout);
    }

    /**
     * @return iterable<string, array{list<Spelled>}>
     */
    public static function providerUnaligned(): iterable
    {
        yield 'shorter' => [[new Spelled('', '1')]];
        yield 'longer' => [[new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2'), new Spelled('', '3')]];
    }

    /**
     * @param list<Spelled> $tokens
     */
    #[DataProvider('providerUnaligned')]
    public function testCheckRefusesAnUnalignedLayoutAtConstruction(array $tokens): void
    {
        $this->expectExceptionMessage('A select-item layout has one token per expression token.');

        new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: new Layout($tokens));
    }
}
