<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(SqlGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(TokenRewriter::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
final class SqlGeneratorTest extends TestCase
{
    public function testGenerateReusesCompletionAnalysisAcrossDifferentPlans(): void
    {
        $grammar = new Grammar('first', [
            'first' => new ProductionRule('first', [new Production([new Terminal('T')])]),
            'second' => new ProductionRule('second', [new Production([new Terminal('U')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $observations = [];
        $lexer->method('isNonOutput')->willReturnCallback(static function (string $terminal) use (&$observations): bool {
            $observations[] = $terminal;
            return false;
        });
        $lexer->method('realizeSequence')->willReturnCallback(static fn (TerminalSequence $sequence): string => implode(' ', $sequence->names()));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('T', $generator->generate(GenerationPlan::all()));
        self::assertSame(['T', 'U'], $observations);
        self::assertSame('U', $generator->generate(GenerationPlan::fromRule('second')));
        self::assertSame(['T', 'U'], $observations);
        self::assertSame('T', $generator->generate(GenerationPlan::all()));
        self::assertSame(['T', 'U'], $observations);
    }

    public function testGenerateUsesTheGrammarEntryPointWithoutDialectKnowledge(): void
    {
        $grammar = new Grammar('custom_entry', [
            'custom_entry' => new ProductionRule('custom_entry', [new Production([new Terminal('CUSTOM')])]),
        ]);
        $plan = GenerationPlan::all();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('realizeSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['CUSTOM']), $plan)->willReturn('custom sql');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('custom sql', $generator->generate($plan));
    }

    public function testGenerateUsesExplicitRulesAndSuppliedParserSemantics(): void
    {
        $grammar = new Grammar('other', [
            'selected' => new ProductionRule('selected', [new Production([new Terminal('RAW')])]),
        ]);
        $plan = GenerationPlan::fromRule('selected')->requiringNonEmpty();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('realizeSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['NORMALIZED', 'RAW']), $plan)->willReturn('normalized');
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(static fn (TerminalSequence $sequence): TerminalSequence =>
            $sequence->replace(0, 0, [$sequence->inserted('NORMALIZED', $sequence->terminals[0], 'test.rule')], 'test.rule'));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer, new TokenRewriter($rule));

        self::assertSame('normalized', $generator->generate($plan));
    }

    public function testGenerateUsesTheSuppliedVersionSpecificRuleResolver(): void
    {
        $grammar = new Grammar('other', [
            'old_rule' => new ProductionRule('old_rule', [new Production([new Terminal('TOKEN')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('realizeSequence')->with(self::callback(static fn (TerminalSequence $sequence): bool => $sequence->names() === ['TOKEN']))->willReturn('token');
        $generator = new SqlGenerator(
            $grammar,
            Factory::create(),
            $lexer,
            null,
            static fn (?string $rule): string => $rule === 'new_rule' ? 'old_rule' : 'missing',
        );

        self::assertSame('token', $generator->generate(GenerationPlan::fromRule('new_rule')));
    }

    public function testGenerateLexicalPlansBypassGrammarDerivation(): void
    {
        $plan = GenerationPlan::lexical('identifier', []);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('generate')->with($plan)->willReturn('name');
        $lexer->expects(self::never())->method('realizeSequence');
        $generator = new SqlGenerator(new Grammar('missing', []), Factory::create(), $lexer);

        self::assertSame('name', $generator->generate($plan));
    }

    public function testGeneratePreservesTheFirstLexicalFailureWithoutRetrying(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $failure = new LexicalException('last failure');
        $lexer->expects(self::once())->method('realizeSequence')->willThrowException($failure);
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectExceptionObject($failure);

        $generator->generate(GenerationPlan::all());
    }

    public function testGenerateAllowsEmptyOutputWhenThePlanAllowsIt(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('realizeSequence')->willReturn('');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('', $generator->generate(GenerationPlan::all()));
    }

    public function testGenerateRejectsUnexpectedEmptyOutputWithoutRetrying(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('version')->willReturn('custom-1');
        $lexer->expects(self::once())->method('realizeSequence')->willReturn('');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('custom-1 generation plan requires non-empty output.');

        $generator->generate(GenerationPlan::all()->requiringNonEmpty());
    }

    public function testGenerateClearsPreviousGrammarTraceBeforeALexicalPlan(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('TOKEN')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('realizeSequence')->willReturn('token');
        $lexer->method('generate')->willReturn('name');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        self::assertSame('token', $generator->generate(GenerationPlan::all()));
        self::assertSame(['TOKEN'], $generator->lastSequence?->names());
        self::assertSame('name', $generator->generate(GenerationPlan::lexical('identifier', [])));
        self::assertNull($generator->lastSequence);
    }

    public function testGenerateReportsOnlyTheLatestRewrittenDerivation(): void
    {
        $grammar = new Grammar('first', [
            'first' => new ProductionRule('first', [new Production([new Terminal('A')])]),
            'second' => new ProductionRule('second', [new Production([new Terminal('B')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('realizeSequence')->willReturn('output');
        $rule = $this->createMock(RewriteRule::class);
        $rule->method('rewrite')->willReturnCallback(static fn (TerminalSequence $sequence): TerminalSequence => $sequence->replace(0, 0, [], 'observed'));
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer, new TokenRewriter($rule));
        $generator->generate(GenerationPlan::all());
        $first = $generator->lastSequence;
        $generator->generate(GenerationPlan::fromRule('second'));
        self::assertSame(['A'], $first?->names());
        self::assertSame(['B'], $generator->lastSequence->names());
        self::assertSame(['observed'], $generator->lastSequence->rewrites);
        self::assertSame('second', $generator->lastSequence->productions[0]->rule);
    }
}
