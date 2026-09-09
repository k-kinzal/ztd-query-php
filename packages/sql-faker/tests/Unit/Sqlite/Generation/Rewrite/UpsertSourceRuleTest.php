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
use SqlFaker\Sqlite\Generation\Rewrite\UpsertSourceRule;

#[CoversClass(UpsertSourceRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class UpsertSourceRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerSources')]
    public function testRewriteSeparatesOnlyAmbiguousInsertSources(TerminalSequence $input, array $expected): void
    {
        $rule = new UpsertSourceRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[0], $result->terminals[0]);
        self::assertSame($result, $rule->rewrite($result));
        $ids = array_column($result->terminals, 'id');
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    /**
     * @return iterable<array{TerminalSequence, list<string>}>
     */
    public static function providerSources(): iterable
    {
        foreach (['cmd', 'ordinary'] as $command) {
            foreach (['ON', 'RETURNING', null] as $upsert) {
                foreach ([true, false] as $from) {
                    foreach (['none', 'where_opt', 'groupby_opt', 'having_opt', 'window_clause', 'orderby_opt', 'limit_opt'] as $tail) {
                        $trace = new DerivationTrace($command);
                        $trace->expand(0, new Production([new Terminal('INSERT'), new NonTerminal('select'), new NonTerminal('upsert')]), 0);
                        $trace->expand(1, new Production([new NonTerminal('oneselect')]), 0);
                        $trace->expand(1, new Production([new Terminal('SELECT'), new Terminal('STAR'), new NonTerminal('from'), new NonTerminal('where_opt'), new NonTerminal('tail')]), 0);
                        $trace->expand(3, new Production($from ? [new Terminal('FROM'), new Terminal('TABLE')] : []), 0);
                        $offset = $from ? 5 : 3;
                        $trace->expand($offset, new Production($tail === 'where_opt' ? [new Terminal('WHERE'), new Terminal('EXPR')] : []), 0);
                        $offset += $tail === 'where_opt' ? 2 : 0;
                        $trace->expand($offset, new Production(in_array($tail, ['none', 'where_opt'], true) ? [] : [new Terminal($tail), new Terminal('EXPR')]), 0);
                        $offset += in_array($tail, ['none', 'where_opt'], true) ? 0 : 2;
                        $trace->expand($offset, new Production($upsert === null ? [] : [new Terminal($upsert), new Terminal('REST')]), 0);
                        $input = $trace->terminals();
                        $expected = $input->names();
                        if ($command === 'cmd' && $upsert === 'ON' && $from && $tail === 'none') {
                            array_splice($expected, 5, 0, ['WHERE', 'INTEGER']);
                        }
                        yield [$input, $expected];
                    }
                }
            }
        }
    }

    public function testRewritePreservesEmptyAndNonSelectCommands(): void
    {
        $empty = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'cmd', 0), new ProductionOccurrence(1, 0, 'select', 0), new ProductionOccurrence(2, 0, 'upsert', 0)]);
        self::assertSame($empty, (new UpsertSourceRule())->rewrite($empty));
        $trace = new DerivationTrace('cmd');
        $trace->expand(0, new Production([new Terminal('DEFAULT'), new Terminal('VALUES'), new NonTerminal('upsert')]), 0);
        $trace->expand(2, new Production([new Terminal('ON')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new UpsertSourceRule())->rewrite($input));
    }

    public function testRewriteAddsTheClauseToTheOuterSourceAndPreservesItsNestedSelect(): void
    {
        $trace = new DerivationTrace('cmd');
        $trace->expand(0, new Production([new NonTerminal('select'), new NonTerminal('upsert')]), 0);
        $trace->expand(0, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('STAR'), new NonTerminal('from'), new NonTerminal('where_opt')]), 0);
        $trace->expand(2, new Production([new Terminal('FROM'), new Terminal('LP'), new NonTerminal('select'), new Terminal('RP')]), 0);
        $trace->expand(4, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(4, new Production([new Terminal('SELECT'), new Terminal('STAR'), new NonTerminal('from'), new NonTerminal('where_opt')]), 0);
        $trace->expand(6, new Production([new Terminal('FROM'), new Terminal('TABLE')]), 0);
        $trace->expand(8, new Production([]), 0);
        $trace->expand(9, new Production([]), 0);
        $trace->expand(9, new Production([new Terminal('ON'), new Terminal('CONFLICT'), new Terminal('DO'), new Terminal('NOTHING')]), 0);
        $input = $trace->terminals();
        $rule = new UpsertSourceRule();
        $result = $rule->rewrite($input);
        self::assertSame(['SELECT', 'STAR', 'FROM', 'LP', 'SELECT', 'STAR', 'FROM', 'TABLE', 'RP', 'WHERE', 'INTEGER', 'ON', 'CONFLICT', 'DO', 'NOTHING'], $result->names());
        self::assertSame(['src/parse.y:on_using:upsert-ambiguity'], $result->rewrites);
        self::assertSame('src/parse.y:on_using:upsert-ambiguity', $result->terminals[9]->rewrite);
        self::assertSame(['cmd', 'select', 'oneselect', 'where_opt'], $result->terminals[9]->rules);
        self::assertSame(-1, $result->terminals[9]->id);
        self::assertSame(-2, $result->terminals[10]->id);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }
    public function testRewriteOnlyCompletesTheLastCompoundOperand(): void
    {
        $trace = new DerivationTrace('cmd');
        $trace->expand(0, new Production([new NonTerminal('select'), new NonTerminal('upsert')]), 0);
        $trace->expand(0, new Production([new NonTerminal('selectnowith')]), 0);
        $trace->expand(0, new Production([new NonTerminal('selectnowith'), new Terminal('UNION'), new NonTerminal('oneselect')]), 1);
        $trace->expand(0, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('STAR'), new NonTerminal('from'), new NonTerminal('where_opt')]), 0);
        $trace->expand(2, new Production([new Terminal('FROM'), new Terminal('LEFT')]), 0);
        $trace->expand(4, new Production([]), 0);
        $trace->expand(5, new Production([new Terminal('SELECT'), new Terminal('STAR'), new NonTerminal('from'), new NonTerminal('where_opt')]), 0);
        $trace->expand(7, new Production([new Terminal('FROM'), new Terminal('RIGHT')]), 0);
        $trace->expand(9, new Production([]), 0);
        $trace->expand(9, new Production([new Terminal('ON'), new Terminal('CONFLICT')]), 0);
        $input = $trace->terminals();
        $result = (new UpsertSourceRule())->rewrite($input);
        self::assertSame(['SELECT', 'STAR', 'FROM', 'LEFT', 'UNION', 'SELECT', 'STAR', 'FROM', 'RIGHT', 'WHERE', 'INTEGER', 'ON', 'CONFLICT'], $result->names());
        self::assertSame($input->productions, $result->productions);
    }

    public function testRewritePreservesAnEarlierFromWhenTheFinalOperandIsValues(): void
    {
        $trace = new DerivationTrace('cmd');
        $trace->expand(0, new Production([new NonTerminal('select'), new NonTerminal('upsert')]), 0);
        $trace->expand(0, new Production([new NonTerminal('selectnowith')]), 0);
        $trace->expand(0, new Production([new NonTerminal('selectnowith'), new Terminal('UNION'), new NonTerminal('oneselect')]), 1);
        $trace->expand(0, new Production([new NonTerminal('oneselect')]), 0);
        $trace->expand(0, new Production([new Terminal('SELECT'), new Terminal('STAR'), new NonTerminal('from'), new NonTerminal('where_opt')]), 0);
        $trace->expand(2, new Production([new Terminal('FROM'), new Terminal('LEFT')]), 0);
        $trace->expand(4, new Production([]), 0);
        $trace->expand(5, new Production([new Terminal('VALUES'), new Terminal('LP'), new Terminal('INTEGER'), new Terminal('RP')]), 0);
        $trace->expand(9, new Production([new Terminal('ON'), new Terminal('CONFLICT')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new UpsertSourceRule())->rewrite($input));
    }
}
