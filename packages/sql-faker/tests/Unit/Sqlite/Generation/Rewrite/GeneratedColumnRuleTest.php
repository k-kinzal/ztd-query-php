<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

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
use SqlFaker\Sqlite\Generation\Rewrite\GeneratedColumnRule;

#[CoversClass(GeneratedColumnRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class GeneratedColumnRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerConstraints')]
    public function testRewriteKeepsOneGeneratedExpressionWithoutDefaults(TerminalSequence $input, array $expected): void
    {
        $rule = new GeneratedColumnRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{TerminalSequence, list<string>}>
     */
    public static function providerConstraints(): iterable
    {
        foreach (['column', 'carglist'] as $scope) {
            foreach ([['AS', 'AS'], ['DEFAULT', 'AS'], ['AS', 'DEFAULT'], ['DEFAULT', 'DEFAULT'], ['AS', 'NULL', 'AS', 'DEFAULT']] as $clauses) {
                $trace = new DerivationTrace($scope);
                $trace->expand(0, new Production(array_fill(0, count($clauses), new NonTerminal('ccons'))), 0);
                $position = 0;
                $expected = [];
                $seen = false;
                foreach ($clauses as $index => $clause) {
                    if ($clause === 'AS') {
                        $trace->expand($position, new Production([new Terminal('AS'), new NonTerminal('generated')]), 0);
                        $trace->expand($position + 1, new Production([new Terminal('LP'), new Terminal('VALUE_' . $index), new Terminal('RP')]), 0);
                        if (!$seen) {
                            array_push($expected, 'AS', 'LP', 'VALUE_' . $index, 'RP');
                        }
                        $seen = true;
                        $position += 4;
                    } else {
                        $trace->expand($position, new Production([new Terminal($clause)]), 0);
                        if ($clause !== 'DEFAULT' || !in_array('AS', $clauses, true)) {
                            $expected[] = $clause;
                        }
                        ++$position;
                    }
                }
                yield [$trace->terminals(), $expected];
            }
        }
    }

    public function testRewriteKeepsGeneratedExpressionsOnSeparateColumns(): void
    {
        $trace = new DerivationTrace('table');
        $trace->expand(0, new Production([new NonTerminal('column'), new Terminal('COMMA'), new NonTerminal('column')]), 0);
        $trace->expand(0, new Production([new NonTerminal('ccons')]), 0);
        $trace->expand(0, new Production([new Terminal('AS'), new NonTerminal('generated')]), 0);
        $trace->expand(1, new Production([new Terminal('FIRST')]), 0);
        $trace->expand(3, new Production([new NonTerminal('ccons')]), 0);
        $trace->expand(3, new Production([new Terminal('AS'), new NonTerminal('generated')]), 0);
        $trace->expand(4, new Production([new Terminal('SECOND')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new GeneratedColumnRule())->rewrite($input));
    }
}
