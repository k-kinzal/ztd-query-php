<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Sqlite\Generation\Rewrite\CompoundSelectRule;

#[CoversClass(CompoundSelectRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class CompoundSelectRuleTest extends TestCase
{
    /**
     * @param non-empty-list<string> $operator
     */
    #[DataProvider('providerOperators')]
    public function testRewriteKeepsNestedAndFinalClausesForEveryCompoundOperator(array $operator): void
    {
        $trace = new DerivationTrace('selectnowith');
        $trace->expand(0, new Production([new NonTerminal('selectnowith'), new NonTerminal('multiselect_op'), new NonTerminal('oneselect')]), 1);
        $trace->expand(0, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(0, new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('orderby_opt'), new NonTerminal('limit_opt')]), 0);
        $trace->expand(1, new Production([new Terminal('LP'), new NonTerminal('selectnowith'), new Terminal('RP')]), 0);
        $trace->expand(2, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(2, new Production([new Terminal('SELECT'), new Terminal('INTEGER'), new NonTerminal('orderby_opt'), new NonTerminal('limit_opt')]), 0);
        $trace->expand(4, new Production([new Terminal('ORDER'), new Terminal('BY'), new Terminal('INTEGER')]), 0);
        $trace->expand(7, new Production([new Terminal('LIMIT'), new Terminal('INTEGER')]), 0);
        $trace->expand(10, new Production([new Terminal('ORDER'), new Terminal('BY'), new Terminal('INTEGER')]), 0);
        $trace->expand(13, new Production([new Terminal('LIMIT'), new Terminal('INTEGER')]), 0);
        $trace->expand(15, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $operator)), 0);
        $trace->expand(15 + count($operator), new Production([new Terminal('SELECT'), new Terminal('INTEGER'), new NonTerminal('orderby_opt'), new NonTerminal('limit_opt')]), 0);
        $trace->expand(17 + count($operator), new Production([new Terminal('ORDER'), new Terminal('BY'), new Terminal('INTEGER')]), 0);
        $trace->expand(20 + count($operator), new Production([new Terminal('LIMIT'), new Terminal('INTEGER')]), 0);
        $input = $trace->terminals();
        $rule = new CompoundSelectRule();
        $result = $rule->rewrite($input);
        self::assertSame(['SELECT', 'LP', 'SELECT', 'INTEGER', 'ORDER', 'BY', 'INTEGER', 'LIMIT', 'INTEGER', 'RP', ...$operator, 'SELECT', 'INTEGER', 'ORDER', 'BY', 'INTEGER', 'LIMIT', 'INTEGER'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{non-empty-list<string>}>
     */
    public static function providerOperators(): array
    {
        return [[['UNION']], [['UNION', 'ALL']], [['INTERSECT']], [['EXCEPT']]];
    }

    public function testRewriteLeavesASingleSelectAndEmptyPriorClausesUnchanged(): void
    {
        $trace = new DerivationTrace('selectnowith');
        $trace->expand(0, new Production([new NonTerminal('selectnowith'), new Terminal('UNION'), new NonTerminal('oneselect')]), 1);
        $trace->expand(0, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('INTEGER'), new NonTerminal('orderby_opt'), new NonTerminal('limit_opt')]), 0);
        $trace->expand(2, new Production([]), 0);
        $trace->expand(2, new Production([]), 0);
        $trace->expand(3, new Production([new Terminal('SELECT'), new Terminal('INTEGER'), new NonTerminal('limit_opt')]), 0);
        $trace->expand(5, new Production([new Terminal('LIMIT'), new Terminal('INTEGER')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new CompoundSelectRule())->rewrite($input));
        $single = new DerivationTrace('selectnowith');
        $single->expand(0, new Production([new NonTerminal('oneselect')]), 0);
        $single->expand(0, new Production([new Terminal('SELECT'), new Terminal('INTEGER'), new NonTerminal('limit_opt')]), 0);
        $single->expand(2, new Production([new Terminal('LIMIT'), new Terminal('INTEGER')]), 0);
        $input = $single->terminals();
        self::assertSame($input, (new CompoundSelectRule())->rewrite($input));
    }
}
