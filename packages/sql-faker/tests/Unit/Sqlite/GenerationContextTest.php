<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\GenerationContext;

#[CoversClass(GenerationContext::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(SqlGenerator::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalProfileSource::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomStringGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\KeywordDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\ValueDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\IdentifierListRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\JoinRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\StrictTableRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\TableOptionRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WithoutRowidRule::class)]
#[UsesClass(\SqlFaker\Sqlite\GrammarAdaptation::class)]
#[UsesClass(\SqlFaker\Sqlite\Grammar\SqliteGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTokenizer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\CompoundSelectRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\UpsertSourceRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\FunctionArgumentRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
final class GenerationContextTest extends TestCase
{
    public function testBindsTheRequestedReleaseAndPreservesTheGrammarStart(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])])]);
        $context = new GenerationContext($grammar, Factory::create(), 'sqlite-3.47.2');
        self::assertSame('sqlite-3.47.2', $context->lexicalGrammar->version());
        self::assertSame('entry', $context->grammar->startSymbol);
        self::assertSame($grammar->ruleMap['entry'], $context->grammar->ruleMap['entry']);
    }

    public function testUsesTheGrammarStartWhenThePlanDoesNotSpecifyOne(): void
    {
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])]),
            'fragment' => new ProductionRule('fragment', [new Production([])]),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'sqlite-3.47.2');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()));
        self::assertSame('', $generator->generate(GenerationPlan::fromRule('fragment')));
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()));
    }

    public function testLeavesThePlanInControlOfNullableProductionChoices(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [
            new Production([]), new Production([new Terminal('SELECT')]),
        ])]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'sqlite-3.47.2');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()->requiringNonEmpty()->withMaxDepth(1)));
        self::assertSame('', $generator->generate(GenerationPlan::constrained('entry', ['entry' => [ProductionPattern::exactly()]])));
    }

    public function testReportsMissingLexicalImplementationWhenTheTerminalIsActuallyGenerated(): void
    {
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])]),
            'missing' => new ProductionRule('missing', [new Production([new Terminal('UNIMPLEMENTED')])]),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'sqlite-3.47.2');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()));
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('UNIMPLEMENTED');
        $generator->generate(GenerationPlan::fromRule('missing'));
    }

    public function testReportsAnUnknownNonterminalWithoutTreatingItAsSqlText(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])])]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'sqlite-3.47.2');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('Unknown grammar rule: missing');
        $generator->generate(GenerationPlan::fromRule('missing'));
    }
}
