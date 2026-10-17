<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem\Operand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection::class)]
#[Medium]
final class RefusedSelectionTest extends TestCase
{
    public function testMessageDescribesTheRefusalWithoutHoldingInputText(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (1,2)');
            self::fail('A row projection has no ordinary scalar SELECT representation.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection::class, $failure->problem);
            self::assertSame('Operand should contain 1 column(s), not 2.', $failure->problem->message());
            self::assertCount(1, $failure->problem->selection->items);
        }
    }
    public function testProblemRetainsTheRowPredicateConstraint(): void
    {
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], where: new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'),new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
        $problem = (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection($selection))->problem();
        self::assertSame([1,2], [$problem->expected,$problem->actual]);
    }
}
