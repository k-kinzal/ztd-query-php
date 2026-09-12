<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule;

#[CoversClass(QuantifiedComparisonRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class QuantifiedComparisonRuleTest extends TestCase
{
    #[DataProvider('providerComparisons')]
    public function testRewriteRestrictsNullSafeEqualityToScalarComparisons(string $scope, string $context, string $operator, string $quantifier, string $expected): void
    {
        $trace = new DerivationTrace($scope);
        $trace->expand(0, new Production([new Terminal('LEFT'), new NonTerminal('comp_op'), new NonTerminal($context), new Terminal('RIGHT')]), 0);
        $trace->expand(1, new Production([new Terminal($operator)]), 0);
        $trace->expand(2, new Production([new Terminal($quantifier)]), 0);
        $input = $trace->terminals();
        $rule = new QuantifiedComparisonRule();
        $result = $rule->rewrite($input);
        self::assertSame(['LEFT', $expected, $quantifier, 'RIGHT'], $result->names());
        self::assertSame($input->terminals[1]->id, $result->terminals[1]->id);
        self::assertSame($input->terminals[1]->ancestors, $result->terminals[1]->ancestors);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, string, string, string, string}>
     */
    public static function providerComparisons(): iterable
    {
        foreach (['ALL', 'ANY_SYM'] as $quantifier) {
            yield ['bool_pri', 'all_or_any', 'EQUAL_SYM', $quantifier, 'EQ'];
            yield ['bool_pri', 'predicate', 'EQUAL_SYM', $quantifier, 'EQUAL_SYM'];
            yield ['ordinary', 'all_or_any', 'EQUAL_SYM', $quantifier, 'EQUAL_SYM'];
            foreach (['EQ', 'GE', 'GT_SYM', 'LE', 'LT', 'NE'] as $operator) {
                yield ['bool_pri', 'all_or_any', $operator, $quantifier, $operator];
            }
        }
    }

    public function testRewritePreservesAnEmptyOrAbsentOperator(): void
    {
        $input = new TerminalSequence([], [], [], [
            new ProductionOccurrence(0, null, 'bool_pri', 0),
            new ProductionOccurrence(1, 0, 'all_or_any', 0),
            new ProductionOccurrence(2, 0, 'comp_op', 0),
            new ProductionOccurrence(3, null, 'bool_pri', 0),
            new ProductionOccurrence(4, 3, 'all_or_any', 0),
        ]);
        self::assertSame($input, (new QuantifiedComparisonRule())->rewrite($input));
    }
}
