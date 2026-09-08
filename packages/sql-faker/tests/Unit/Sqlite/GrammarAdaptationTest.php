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

    public function testWithStatementRulesPreservesEveryImportedAlternative(): void
    {
        $cmd = new ProductionRule('cmd', [
            new Production([new Terminal('DELETE'), new NonTerminal('orderby_opt')]),
        ]);

        self::assertSame($cmd->alternatives, (new GrammarAdaptation())->withStatementRules([], $cmd)['delete']->alternatives);
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
}
