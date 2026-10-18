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
    public function testRightAllowsParenthesizedRetainedClauses(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)], limit: new RowLimit(new NumberLiteral('1')));
        $right = new \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery(new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection));
        (new SetOperands())->right($right);
        $union = new SetOperation(new Select([], [new SelectExpression(new NumberLiteral('3'))]), SetOperator::Union, null, $right);
        self::assertSame($right, $union->right);
    }

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
    public function testUnenclosedOnlyOwnsTheFirstMissingEnclosure(): void
    {
        $one = static fn (): Select => new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $enclosed = static fn (): \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery => new \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery($one());
        $invalid = new SetOperation($enclosed(), SetOperator::Union, null, $one());
        $rules = new SetOperands();
        $check = static fn (SetOperation $query): bool => $rules->unenclosed($query->left, $query->operator, $query->right);
        self::assertTrue($check($invalid));
        self::assertFalse($check(new SetOperation($invalid, SetOperator::Union, null, $one())));
        self::assertFalse($check(new SetOperation($one(), SetOperator::Union, null, $enclosed())));
        self::assertTrue($check(new SetOperation(new SetOperation($enclosed(), SetOperator::Union, null, $enclosed()), SetOperator::Union, null, $one())));
    }
}
