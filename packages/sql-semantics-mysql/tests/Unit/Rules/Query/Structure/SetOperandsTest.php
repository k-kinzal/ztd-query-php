<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SetOperands;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Statement\Node;

#[CoversClass(SetOperands::class)]
#[Small]
final class SetOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(Select, Select): Node}>
     */
    public static function providerChains(): iterable
    {
        yield 'ordinary union' => [static fn (Select $left, Select $right): Node => new SetOperation($left, SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('2'))]))];
        yield 'leading union' => [static fn (Select $left, Select $right): Node => new LeadingUnion($left, null, $right)];
        yield 'ordered union' => [static fn (Select $left, Select $right): Node => new OrderedSetOperation($left, SetOperator::Union, null, $right, limit: new RowLimit(new NumberLiteral('1')))];
    }

    /**
     * @param Closure(Select, Select): Node $construct The enclosing set-chain constructor
     */
    #[DataProvider('providerChains')]
    public function testLeftCannotEscapeThroughAnOrderedSetVariant(Closure $construct): void
    {
        $left = new Select([], [new SelectExpression(new NumberLiteral('1'))], orderBy: [new OrderItem(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')))]);
        $right = new Select([], [new SelectExpression(new NumberLiteral('2'))], limit: new RowLimit(new NumberLiteral('1')));
        $this->expectExceptionMessage('A SELECT before a set operator has no unparenthesized ORDER BY, INTO or PROCEDURE ANALYSE.');

        $construct($left, $right);
    }

    public function testALegacyLimitRemainsConstructible(): void
    {
        $left = new Select([], [new SelectExpression(new NumberLiteral('1'))], limit: new RowLimit(new NumberLiteral('1')));
        $right = new Select([], [new SelectExpression(new NumberLiteral('2'))]);
        $union = new SetOperation($left, SetOperator::Union, null, $right);

        self::assertSame($left, $union->left);
    }
}
