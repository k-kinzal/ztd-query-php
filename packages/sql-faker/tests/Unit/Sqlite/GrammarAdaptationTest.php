<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\GrammarAdaptation;
use SqlFaker\Sqlite\LexicalGrammar;

#[CoversClass(GrammarAdaptation::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class GrammarAdaptationTest extends TestCase
{
    public function testAdaptedGivesEachStatementKindARuleOfItsOwn(): void
    {
        $grammar = new Grammar('cmd', [
            'cmd' => new ProductionRule('cmd', [
                new Production([new Terminal('DELETE'), new Terminal('FROM'), new NonTerminal('nm')]),
                new Production([new Terminal('UPDATE'), new NonTerminal('nm')]),
            ]),
        ]);

        self::assertSame(
            ['cmd', 'delete', 'update'],
            array_keys((new GrammarAdaptation())->adapted($grammar)->ruleMap),
        );
    }

    public function testAdaptedLeavesAGrammarWithoutACmdRuleAlone(): void
    {
        $grammar = new Grammar('expr', ['expr' => new ProductionRule('expr', [new Production([new Terminal('NULL')])])]);

        self::assertSame(['expr'], array_keys((new GrammarAdaptation())->adapted($grammar)->ruleMap));
    }

    public function testWithStrictTableOptionMakesStrictReachableAsATableOption(): void
    {
        $ruleMap = (new GrammarAdaptation())->withStrictTableOption([
            'table_option' => new ProductionRule('table_option', [new Production([new NonTerminal('nm')])]),
        ]);

        self::assertEquals(
            [new Production([new NonTerminal('nm')]), new Production([new Terminal(LexicalGrammar::STRICT_TABLE_OPTION)])],
            $ruleMap['table_option']->alternatives,
        );
    }

    public function testWithStrictTableOptionLeavesAGrammarWithoutTableOptionsAlone(): void
    {
        self::assertSame([], (new GrammarAdaptation())->withStrictTableOption([]));
    }

    public function testWithStatementRulesLeavesOutADeleteThatOnlyASpecialBuildAccepts(): void
    {
        $cmd = new ProductionRule('cmd', [
            new Production([new Terminal('DELETE'), new NonTerminal('orderby_opt')]),
        ]);

        self::assertSame([], array_keys((new GrammarAdaptation())->withStatementRules([], $cmd)));
    }

    public function testStatementAlternativesSortsAnAlternativeByTheKeywordItLeadsWith(): void
    {
        $delete = new Production([new Terminal('DELETE'), new NonTerminal('nm')]);
        $update = new Production([new Terminal('UPDATE'), new NonTerminal('nm')]);
        $groups = (new GrammarAdaptation())->statementAlternatives(new ProductionRule('cmd', [$delete, $update]));

        self::assertSame([$delete], $groups['delete']);
        self::assertSame([$update], $groups['update']);
    }

    public function testStatementAlternativesSortsAnAlternativeThatBeginsWithAnOptionalWithClause(): void
    {
        $delete = new Production([new NonTerminal('with'), new Terminal('DELETE')]);
        $insert = new Production([new NonTerminal('with'), new NonTerminal('insert_cmd')]);
        $groups = (new GrammarAdaptation())->statementAlternatives(new ProductionRule('cmd', [$delete, $insert]));

        self::assertSame([$delete], $groups['delete']);
        self::assertSame([$insert], $groups['insert']);
    }

    public function testStatementAlternativesKeepsAlterAndDropOnlyWhenTheyActOnATable(): void
    {
        $alterTable = new Production([new Terminal('ALTER'), new Terminal('TABLE')]);
        $dropTable = new Production([new Terminal('DROP'), new Terminal('TABLE')]);
        $dropIndex = new Production([new Terminal('DROP'), new Terminal('INDEX')]);
        $groups = (new GrammarAdaptation())->statementAlternatives(
            new ProductionRule('cmd', [$alterTable, $dropTable, $dropIndex]),
        );

        self::assertSame([$alterTable], $groups['alter_table']);
        self::assertSame([$dropTable], $groups['drop_table']);
    }

    public function testWithoutWithinGroupExpressionsDropsWhatTheTokenizerCannotReadBack(): void
    {
        $kept = new Production([new Terminal('NULL')]);
        $ruleMap = (new GrammarAdaptation())->withoutWithinGroupExpressions([
            'expr' => new ProductionRule('expr', [$kept, new Production([new Terminal('WITHIN')])]),
        ]);

        self::assertSame([$kept], $ruleMap['expr']->alternatives);
    }

    public function testWithoutWithinGroupExpressionsLeavesAGrammarWithoutExpressionsAlone(): void
    {
        self::assertSame([], (new GrammarAdaptation())->withoutWithinGroupExpressions([]));
    }

    public function testWithoutFrameOnlyWindowsDropsAWindowThatCanWriteNothing(): void
    {
        $kept = new Production([new Terminal('PARTITION'), new NonTerminal('frame_opt')]);
        $ruleMap = (new GrammarAdaptation())->withoutFrameOnlyWindows([
            'window' => new ProductionRule('window', [$kept, new Production([new NonTerminal('frame_opt')])]),
        ]);

        self::assertSame([$kept], $ruleMap['window']->alternatives);
    }

    public function testWithoutFrameOnlyWindowsLeavesAGrammarWithoutWindowsAlone(): void
    {
        self::assertSame([], (new GrammarAdaptation())->withoutFrameOnlyWindows([]));
    }

    public function testIsFrameOnlyWindowAcceptsAnAlternativeWithNoKeywordOfItsOwn(): void
    {
        $alternative = new Production([new NonTerminal('nm'), new NonTerminal('frame_opt')]);

        self::assertTrue((new GrammarAdaptation())->isFrameOnlyWindow($alternative));
    }

    public function testIsFrameOnlyWindowRejectsAnAlternativeThatWritesAKeyword(): void
    {
        $alternative = new Production([new Terminal('PARTITION'), new NonTerminal('frame_opt')]);

        self::assertFalse((new GrammarAdaptation())->isFrameOnlyWindow($alternative));
    }
    public function testWithResolvedSymbolsMakesImplicitTokensExplicit(): void
    {
        $rules = ['stmt' => new ProductionRule('stmt', [new Production([
            new NonTerminal('stmt'), new NonTerminal('implicit'), new Terminal('EXPLICIT'),
        ])])];
        $adapted = (new GrammarAdaptation())->withResolvedSymbols($rules);

        self::assertEquals([
            new NonTerminal('stmt'), new Terminal('implicit'), new Terminal('EXPLICIT'),
        ], $adapted['stmt']->alternatives[0]->symbols);
        self::assertInstanceOf(NonTerminal::class, $rules['stmt']->alternatives[0]->symbols[1]);
    }
}
