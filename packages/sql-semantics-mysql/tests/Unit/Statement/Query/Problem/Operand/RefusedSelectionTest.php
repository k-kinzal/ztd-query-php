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
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $statement->query);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection($statement->query->selection);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->message());
        self::assertCount(1, $problem->selection->items);
        self::assertSame('SELECT (1, 2)', $statement->toString());
    }
    public function testProblemRetainsTheRowPredicateConstraint(): void
    {
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], where: new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'),new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
        $problem = (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection($selection))->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([1,2], [$problem->expected,$problem->actual]);
    }
}
