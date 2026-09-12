<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

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
use SqlFaker\Sqlite\Generation\Rewrite\FunctionArgumentRule;

#[CoversClass(FunctionArgumentRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class FunctionArgumentRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerArgumentLists')]
    public function testRewriteLimitsDirectFunctionArguments(TerminalSequence $input, array $expected): void
    {
        $rule = new FunctionArgumentRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[2], $result->terminals[2]);
        self::assertNotEmpty($input->terminals);
        self::assertNotEmpty($result->terminals);
        self::assertSame($input->terminals[array_key_last($input->terminals)], $result->terminals[array_key_last($result->terminals)]);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{TerminalSequence, list<string>}>
     */
    public static function providerArgumentLists(): iterable
    {
        foreach ([['expr', 'idj', true], ['expr', 'nm', false], ['ordinary', 'idj', false]] as [$scope, $name, $limited]) {
            foreach ([1, 126, 127, 128, 130] as $count) {
                $trace = new DerivationTrace($scope);
                $trace->expand(0, new Production([new NonTerminal($name), new Terminal('LP'), new NonTerminal('exprlist'), new Terminal('RP'), new Terminal('TAIL')]), 0);
                $trace->expand(0, new Production([new Terminal('NAME')]), 0);
                $trace->expand(2, new Production([new NonTerminal('nexprlist')]), 0);
                for ($remaining = $count; $remaining > 1; --$remaining) {
                    $trace->expand(2, new Production([new NonTerminal('nexprlist'), new Terminal('COMMA'), new NonTerminal('expr')]), 0);
                }
                $trace->expand(2, new Production([new NonTerminal('expr')]), 1);
                $expected = ['NAME', 'LP'];
                for ($index = 0; $index < $count; ++$index) {
                    $trace->expand(2 + 4 * $index, new Production([new NonTerminal('expr'), new Terminal('PLUS'), new Terminal('INTEGER')]), 0);
                    $trace->expand(2 + 4 * $index, new Production([new Terminal('ARG_' . $index)]), 0);
                    if (!$limited || $index < 127) {
                        if ($index !== 0) {
                            $expected[] = 'COMMA';
                        }
                        array_push($expected, 'ARG_' . $index, 'PLUS', 'INTEGER');
                    }
                }
                yield [$trace->terminals(), [...$expected, 'RP', 'TAIL']];
            }
        }
    }

    public function testRewritePreservesEmptyArgumentsAndStandaloneLists(): void
    {
        $trace = new DerivationTrace('expr');
        $trace->expand(0, new Production([new NonTerminal('idj'), new Terminal('LP'), new NonTerminal('exprlist'), new Terminal('RP')]), 0);
        $trace->expand(0, new Production([new Terminal('NAME')]), 0);
        $trace->expand(2, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new FunctionArgumentRule())->rewrite($input));
        $standalone = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'exprlist', 0)]);
        self::assertSame($standalone, (new FunctionArgumentRule())->rewrite($standalone));
    }
}
