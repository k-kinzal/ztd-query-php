<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(SqlGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\TerminationCost::class)]
#[UsesClass(LexicalException::class)]
final class SqlGeneratorTest extends TestCase
{
    public function testGenerateUsesTheGrammarEntryPointWithoutDialectKnowledge(): void
    {
        $grammar = new Grammar('custom_entry', [
            'custom_entry' => new ProductionRule('custom_entry', [new Production([new Terminal('CUSTOM')])]),
        ]);
        $plan = GenerationPlan::all();
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('supports')->willReturn(true);
        $lexer->expects(self::once())->method('realize')->with(['CUSTOM'], $plan)->willReturn('custom sql');
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
        $lexer->method('supports')->willReturn(true);
        $lexer->expects(self::once())->method('realize')->with(['NORMALIZED', 'RAW'], $plan)->willReturn('normalized');
        $generator = new SqlGenerator(
            $grammar,
            Factory::create(),
            $lexer,
            static fn (array $terminals): array => ['NORMALIZED', ...$terminals],
        );

        self::assertSame('normalized', $generator->generate($plan));
    }

    public function testGenerateUsesTheSuppliedVersionSpecificRuleResolver(): void
    {
        $grammar = new Grammar('other', [
            'old_rule' => new ProductionRule('old_rule', [new Production([new Terminal('TOKEN')])]),
        ]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('supports')->willReturn(true);
        $lexer->expects(self::once())->method('realize')->with(['TOKEN'])->willReturn('token');
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
        $lexer->expects(self::never())->method('realize');
        $generator = new SqlGenerator(new Grammar('missing', []), Factory::create(), $lexer);

        self::assertSame('name', $generator->generate($plan));
    }

    public function testGenerateRetriesLexicalFailuresUntilRealizationSucceeds(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $attempt = 0;
        $lexer->expects(self::exactly(2))->method('realize')->willReturnCallback(
            static function () use (&$attempt): string {
                if ($attempt++ === 0) {
                    throw new LexicalException('retry');
                }
                return 'success';
            },
        );
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('success', $generator->generate(GenerationPlan::all()));
    }

    public function testGenerateExhaustsTheRetryBudgetAndPreservesTheLastFailure(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $failure = new LexicalException('last failure');
        $lexer->expects(self::exactly(32))->method('realize')->willThrowException($failure);
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectExceptionObject($failure);

        $generator->generate(GenerationPlan::all());
    }

    public function testGenerateAllowsEmptyOutputWhenThePlanAllowsIt(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->expects(self::once())->method('realize')->willReturn('');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);

        self::assertSame('', $generator->generate(GenerationPlan::all()));
    }

    public function testGenerateRejectsRepeatedEmptyOutputForANonEmptyPlan(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $lexer = $this->createMock(LexicalGrammar::class);
        $lexer->method('supports')->willReturn(true);
        $lexer->method('version')->willReturn('custom-1');
        $lexer->expects(self::exactly(32))->method('realize')->willReturn('');
        $generator = new SqlGenerator($grammar, Factory::create(), $lexer);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('custom-1 generation plan requires non-empty output.');

        $generator->generate(GenerationPlan::all()->requiringNonEmpty());
    }
}
