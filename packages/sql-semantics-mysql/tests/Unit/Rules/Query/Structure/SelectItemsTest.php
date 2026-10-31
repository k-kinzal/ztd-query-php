<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SelectItems;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Statement\Node;

#[CoversClass(SelectItems::class)]
#[Small]
final class SelectItemsTest extends TestCase
{
    public function testCheckedAcceptsABareStarBeforeAnExpression(): void
    {
        $items = [new Star(), new SelectExpression(new NumberLiteral('1'))];

        self::assertSame($items, (new SelectItems())->checked($items, 'empty'));
    }

    /**
     * @return iterable<string, array{list<Node>}>
     */
    public static function providerMisplaced(): iterable
    {
        yield 'after expression' => [[new SelectExpression(new NumberLiteral('1')), new Star()]];
        yield 'after star' => [[new Star(), new Star()]];
    }

    /**
     * @param list<Node> $items
     */
    #[DataProvider('providerMisplaced')]
    public function testCheckedRefusesMisplacedStarsInSelect(array $items): void
    {
        $this->expectExceptionMessage('A select list item is an expression or a qualified star, and an unqualified star comes first.');

        new Select([], $items);
    }

}
