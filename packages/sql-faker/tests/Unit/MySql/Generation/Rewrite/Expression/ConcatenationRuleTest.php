<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Expression;

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
use SqlFaker\MySql\Generation\Rewrite\Expression\ConcatenationRule;

#[CoversClass(ConcatenationRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class ConcatenationRuleTest extends TestCase
{
    public function testRewritePreservesNestedOperandsAndPrecedence(): void
    {
        $trace = new DerivationTrace('simple_expr');
        $trace->expand(0, new Production([new NonTerminal('simple_expr'), new Terminal('OR_OR_SYM'), new NonTerminal('simple_expr')]), 0);
        $trace->expand(0, new Production([new NonTerminal('simple_expr'), new Terminal('OR_OR_SYM'), new NonTerminal('simple_expr')]), 0);
        $trace->expand(0, new Production([new Terminal('A')]), 1);
        $trace->expand(2, new Production([new Terminal('B')]), 1);
        $trace->expand(4, new Production([new Terminal('C')]), 1);
        $input = $trace->terminals();
        $rule = new ConcatenationRule();
        $result = $rule->rewrite($input);
        self::assertSame(['CONCAT_FUNCTION_NAME', '(', 'CONCAT_FUNCTION_NAME', '(', 'A', ',', 'B', ')', ',', 'C', ')'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[0], $result->terminals[4]);
        self::assertSame($input->terminals[2], $result->terminals[6]);
        self::assertSame($input->terminals[4], $result->terminals[9]);
        self::assertSame($result, $rule->rewrite($result));
        self::assertCount(2, $result->operations);
        $ids = array_column($result->terminals, 'id');
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    #[DataProvider('providerOtherProductions')]
    public function testRewritePreservesUnrelatedAndEmptyProductions(TerminalSequence $input): void
    {
        self::assertSame($input, (new ConcatenationRule())->rewrite($input));
    }

    /**
     * @return iterable<array{TerminalSequence}>
     */
    public static function providerOtherProductions(): iterable
    {
        yield [TerminalSequence::fromNames(['A', 'OR2_SYM', 'B'])];
        yield [new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'simple_expr', 0)])];
        $trace = new DerivationTrace('simple_expr');
        $trace->expand(0, new Production([new NonTerminal('unrelated')]), 0);
        $trace->expand(0, new Production([new Terminal('OR_OR_SYM')]), 0);
        yield [$trace->terminals()];
    }
}
