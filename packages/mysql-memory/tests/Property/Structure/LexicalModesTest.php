<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Directly constructed lexical variants execute in their declared session modes.
 */
#[CoversNothing]
#[Medium]
final class LexicalModesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * @throws \MySqlMemory\Error\SqlError When the directly constructed query cannot execute
     */
    #[DataProvider('providerSeeds')]
    public function testModeSpecificStructuresPreserveTheirMeaning(string $seed): void
    {
        $choices = new Choices('lexical-structures:' . $seed);
        $high = $choices->one([true, false]);
        $concat = $choices->one([true, false]);
        $left = $choices->integer(0, 20);
        $right = $choices->integer(0, 20);
        $negation = $high ? new Unary(UnaryOperator::Not, new NumberLiteral((string) $left), true) : new Not(new NumberLiteral((string) $left));
        $pipes = $concat ? new Concatenation(new NumberLiteral((string) $left), new NumberLiteral((string) $right)) : new Logical(LogicalOperator::Or, new NumberLiteral((string) $left), new NumberLiteral((string) $right), true);
        $comparison = $high ? new Comparison(ComparisonOperator::Equal, new Unary(UnaryOperator::Not, new NumberLiteral((string) $left), true), new NumberLiteral((string) $right)) : new Not(new Comparison(ComparisonOperator::Equal, new NumberLiteral((string) $left), new NumberLiteral((string) $right)));
        $statement = new Select([], [new SelectExpression($negation, new Name('negation')), new SelectExpression($pipes, new Name('pipes')), new SelectExpression($comparison, new Name('precedence'))]);
        $mode = implode(',', array_filter([$high ? 'HIGH_NOT_PRECEDENCE' : '', $concat ? 'PIPES_AS_CONCAT' : '']));
        $reply = (new Execution())->run($statement, $choices->one(['8.0.44', '8.4.7', '9.1.0']), mode: $mode);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) (int) ($left === 0), $concat ? (string) $left . $right : (string) (int) ($left !== 0 || $right !== 0), (string) (int) ($high ? (int) ($left === 0) === $right : $left !== $right)]], $reply->rows);
    }
}
