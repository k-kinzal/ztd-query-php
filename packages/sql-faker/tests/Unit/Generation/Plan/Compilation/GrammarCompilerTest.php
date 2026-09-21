<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Plan\Compilation\GrammarCompiler;
use SqlFaker\Generation\Plan\Compilation\PreparedGrammar;
use SqlFaker\Generation\Plan\Compilation\Scope;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Plan\RulePlan;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(GrammarCompiler::class)]
#[UsesClass(PreparedGrammar::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationAnalyzer::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(Scope::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(RulePlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class GrammarCompilerTest extends TestCase
{
    public function testCompileListItemsPreserveOutputOrderForBothRecursionDirections(): void
    {
        $grammar = new Grammar('list', [
            'list' => new ProductionRule('list', [
                new Production([new NonTerminal('list'), new Terminal(','), new NonTerminal('item')]),
                new Production([new NonTerminal('item'), new Terminal(';'), new NonTerminal('list')]),
                new Production([new NonTerminal('item')]),
            ]),
            'item' => new ProductionRule('item', [new Production([new Terminal('ID')]), new Production([new Terminal('INTEGER')])]),
        ]);
        $plan = RulePlan::any()->withItems(
            RulePlan::any()->withRule('item', RulePlan::any()->allowing(ProductionPattern::exactly('ID'))),
            RulePlan::any()->withRule('item', RulePlan::any()->allowing(ProductionPattern::exactly('INTEGER'))),
        );
        $prepared = (new GrammarCompiler($grammar))->compile('list', ['list' => $plan]);
        $enumerate = static function (string $rule) use (&$enumerate, $prepared): array {
            $language = [];
            foreach ($prepared->grammar->ruleMap[$rule]->alternatives as $production) {
                $prefixes = [''];
                foreach ($production->symbols as $symbol) {
                    $suffixes = $symbol instanceof NonTerminal ? $enumerate($symbol->value) : [$symbol->value()];
                    $combined = [];
                    foreach ($prefixes as $prefix) {
                        foreach ($suffixes as $suffix) {
                            $combined[] = trim($prefix . ' ' . $suffix);
                        }
                    }
                    $prefixes = $combined;
                }
                $language = [...$language, ...$prefixes];
            }
            return array_values(array_unique($language));
        };
        $language = $enumerate($prepared->grammar->startSymbol);
        sort($language);
        self::assertSame(['ID , INTEGER', 'ID ; INTEGER'], $language);
        self::assertCount(3, $grammar->ruleMap['list']->alternatives);
    }

    public function testHasChildrenConditionsPruneIncompatibleProductions(): void
    {
        $grammar = new Grammar('pair', [
            'pair' => new ProductionRule('pair', [new Production([new NonTerminal('value')]), new Production([new NonTerminal('value'), new NonTerminal('value')])]),
            'value' => new ProductionRule('value', [new Production([new Terminal('ID')]), new Production([new Terminal('INTEGER')])]),
        ]);
        $plan = RulePlan::any()->withChild('value', 0, RulePlan::any()->allowing(ProductionPattern::exactly('ID')))
            ->withChild('value', 1, RulePlan::any()->allowing(ProductionPattern::exactly('INTEGER')));
        $prepared = (new GrammarCompiler($grammar))->compile('pair', ['pair' => $plan]);
        $choices = $prepared->grammar->ruleMap[$prepared->grammar->startSymbol]->alternatives;
        self::assertCount(1, $choices);
        $first = $choices[0]->symbols[0]->value();
        $second = $choices[0]->symbols[1]->value();
        self::assertSame('ID', $prepared->grammar->ruleMap[$first]->alternatives[0]->symbols[0]->value());
        self::assertSame('INTEGER', $prepared->grammar->ruleMap[$second]->alternatives[0]->symbols[0]->value());
    }

    public function testCompileContradictoryProductionConditionsAreRejected(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new Terminal('T')])])]);
        $this->expectException(GenerationException::class);
        (new GrammarCompiler($grammar))->compile('root', ['root' => RulePlan::any()->allowing(ProductionPattern::exactly('U'))]);
    }


    public function testValidateAcceptsNestedChildAndListDeclarations(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new Terminal('T')])])]);
        $compiler = new GrammarCompiler($grammar);
        $compiler->validate(['root' => RulePlan::any()->withChild('root', 0, RulePlan::any())->withItems(RulePlan::any()->withRule('root', RulePlan::any()))]);
        self::assertCount(1, $compiler->compile('root', [])->grammar->ruleMap);
    }

    public function testRuleMemoizesRecursiveScopesBeforeWalkingTheirChildren(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new NonTerminal('root')]), new Production([new Terminal('T')])])]);
        $compiler = new GrammarCompiler($grammar);
        $scope = new Scope();
        self::assertSame($compiler->rule('root', $scope), $compiler->rule('root', $scope));
        $prepared = $compiler->compile('root', []);
        self::assertCount(1, $prepared->grammar->ruleMap);
        self::assertCount(2, $prepared->grammar->ruleMap[$prepared->grammar->startSymbol]->alternatives);
    }

    public function testItemRejectsNullableAndBranchingRecursionForOrderedLists(): void
    {
        $compiler = new GrammarCompiler(new Grammar('list', []));
        $item = RulePlan::any();
        self::assertFalse($compiler->item('list', new Production([]), [$item]));
        self::assertFalse($compiler->item('list', new Production([new NonTerminal('list'), new NonTerminal('list')]), [$item, $item]));
        self::assertNull($compiler->item('list', new Production([]), null));
    }

    public function testSymbolsKeepInfeasibleBranchesFromInvalidatingOtherAlternatives(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('blocked')]), new Production([new Terminal('OK')])]),
            'blocked' => new ProductionRule('blocked', [new Production([new Terminal('T')])]),
        ]);
        $prepared = (new GrammarCompiler($grammar))->compile('root', ['blocked' => RulePlan::any()->allowing(ProductionPattern::exactly('U'))]);
        $costs = new \SqlFaker\Generation\Derivation\TerminationCost($prepared->grammar, static fn (string $terminal): bool => true, 0, 1);
        self::assertSame(1, $costs->of($prepared->grammar->startSymbol));
        self::assertSame('OK', $prepared->grammar->ruleMap[$prepared->grammar->startSymbol]->alternatives[1]->symbols[0]->value());
    }


    public function testSymbolsApplyChildConditionsToARecursiveListTail(): void
    {
        $grammar = new Grammar('list', [
            'list' => new ProductionRule('list', [
                new Production([new NonTerminal('list'), new Terminal(','), new Terminal('ID')]),
                new Production([new Terminal('ID')]),
                new Production([new Terminal('INTEGER')]),
            ]),
        ]);
        $plan = RulePlan::any()->withItems(RulePlan::any(), RulePlan::any())
            ->withChild('list', 0, RulePlan::any()->allowing(ProductionPattern::exactly('INTEGER')));
        $prepared = (new GrammarCompiler($grammar))->compile('list', ['list' => $plan]);
        $root = $prepared->grammar->ruleMap[$prepared->grammar->startSymbol]->alternatives;
        self::assertCount(1, $root);
        $tail = $prepared->grammar->ruleMap[$root[0]->symbols[0]->value()]->alternatives;
        self::assertCount(1, $tail);
        self::assertSame('INTEGER', $tail[0]->symbols[0]->value());
    }

}
