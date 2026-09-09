<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\OverlapsArgumentsRule;

#[CoversClass(OverlapsArgumentsRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TerminalOccurrence::class)]
final class OverlapsArgumentsRuleTest extends TestCase
{
    public function testRewriteCompletesOnlyRowsBelongingToOverlaps(): void
    {
        $trace = new DerivationTrace('a_expr');
        $trace->expand(0, new Production([new NonTerminal('row'), new Terminal('OVERLAPS'), new NonTerminal('row')]), 0);
        $trace->expand(0, new Production([new Terminal('ROW'), new Terminal('('), new Terminal(')')]), 1);
        $trace->expand(4, new Production([new Terminal('ROW'), new Terminal('('), new Terminal(')')]), 1);
        $input = $trace->terminals();
        $result = (new OverlapsArgumentsRule())->rewrite($input);
        self::assertSame(['ROW', '(', 'NULL_P', ',', 'NULL_P', ')', 'OVERLAPS', 'ROW', '(', 'NULL_P', ',', 'NULL_P', ')'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, (new OverlapsArgumentsRule())->rewrite($result));
        $ordinary = TerminalSequence::fromNames(['ROW', '(', ')']);
        self::assertSame($ordinary, (new OverlapsArgumentsRule())->rewrite($ordinary));
    }

    public function testElementsExcludesNestedExpressions(): void
    {
        $input = new TerminalSequence([], [], [], [
            new ProductionOccurrence(0, null, 'row', 0),
            new ProductionOccurrence(1, 0, 'expr_list', 1),
            new ProductionOccurrence(2, 1, 'expr_list', 0),
            new ProductionOccurrence(3, 2, 'a_expr', 0),
            new ProductionOccurrence(4, 3, 'a_expr', 0),
            new ProductionOccurrence(5, 1, 'a_expr', 0),
        ]);
        self::assertSame([3, 5], (new OverlapsArgumentsRule())->elements($input, 0));
    }

    public function testPairPreservesTwoChosenExpressionsWithoutRewriting(): void
    {
        $trace = new DerivationTrace('row');
        $trace->expand(0, new Production([new Terminal('('), new NonTerminal('a_expr'), new Terminal(','), new NonTerminal('a_expr'), new Terminal(')')]), 2);
        $trace->expand(1, new Production([new Terminal('VALUE')]), 0);
        $trace->expand(3, new Production([new Terminal('VALUE')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new OverlapsArgumentsRule())->pair($input, 0));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerRows')]
    public function testPairRetainsOnlyTheFirstTwoElementsAndTheirIdentities(TerminalSequence $input, array $expected): void
    {
        $rule = new OverlapsArgumentsRule();
        $result = $rule->pair($input, 0);
        self::assertSame($expected, $result->names());
        self::assertSame($input->terminals[2], $result->terminals[2]);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame(count($result->terminals), count(array_unique(array_map(static fn (TerminalOccurrence $terminal): int => $terminal->id, $result->terminals))));
        self::assertSame($result, $rule->pair($result, 0));
    }

    /**
     * @return iterable<string, array{TerminalSequence, list<string>}>
     */
    public static function providerRows(): iterable
    {
        foreach ([1, 3] as $arity) {
            $productions = [new ProductionOccurrence(0, null, 'row', 0), new ProductionOccurrence(1, 0, 'expr_list', 0)];
            $terminals = [new TerminalOccurrence('ROW', 10, [0], ['row']), new TerminalOccurrence('(', 11, [0], ['row'])];
            for ($index = 0; $index < $arity; ++$index) {
                if ($index !== 0) {
                    $terminals[] = new TerminalOccurrence(',', 12 + $index * 2, [0, 1], ['row', 'expr_list']);
                }
                $productions[] = new ProductionOccurrence(2 + $index, 1, 'a_expr', 0);
                $terminals[] = new TerminalOccurrence('VALUE_' . $index, 13 + $index * 2, [0, 1, 2 + $index], ['row', 'expr_list', 'a_expr']);
            }
            $terminals[] = new TerminalOccurrence(')', 30, [0], ['row']);
            yield 'arity ' . $arity => [new TerminalSequence($terminals, $terminals, [], $productions), ['ROW', '(', 'VALUE_0', ',', $arity === 1 ? 'NULL_P' : 'VALUE_1', ')']];
        }
    }

    public function testPairLeavesMissingRowsUntouched(): void
    {
        $input = TerminalSequence::fromNames(['OTHER']);
        self::assertSame($input, (new OverlapsArgumentsRule())->pair($input, 99));
    }

    public function testRewriteIgnoresOverlapsWithoutAnExpressionScope(): void
    {
        $input = new TerminalSequence([new TerminalOccurrence('OVERLAPS', 1), new TerminalOccurrence('ROW', 2, [0], ['row'])], [], [], [new ProductionOccurrence(0, null, 'row', 0)]);
        self::assertSame($input, (new OverlapsArgumentsRule())->rewrite($input));
    }
}
