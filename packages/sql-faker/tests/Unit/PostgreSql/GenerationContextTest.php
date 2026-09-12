<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

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
use SqlFaker\PostgreSql\GenerationContext;

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
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Option\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RandomCharacters::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\HashBoundLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\CopySourceRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\FetchWithTiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\FunctionNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\HashPartitionBoundRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LimitOffsetRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\LookaheadRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OperatorArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\OverlapsArgumentsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\PublicationObjectRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\RelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\TimeZoneIntervalRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Grammar\PgGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgTokenizer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\ConstraintAttributesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\AnyRelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\IndirectionStarRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SelectOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\TableFunctionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\TypeModifierRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\IdentityOptionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\WithinGroupRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\ColumnNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\AliasRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\SubstringRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\RangeFunctionOrdinalityRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonOptionsRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\ConstraintCapabilitiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\ForeignKeyActionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SchemaElementsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\ParserOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonTablePathRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\AggregateArgumentRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\ParserNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\NumericContextRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\OperatorDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Completion\CompletionWitness::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\LexicalDefinition::class)]
final class GenerationContextTest extends TestCase
{
    public function testBindsTheRequestedReleaseAndPreservesTheGrammarStart(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])])]);
        $context = new GenerationContext($grammar, Factory::create(), 'pg-17.2');
        self::assertSame('pg-17.2', $context->lexicalGrammar->version());
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
        $context = new GenerationContext($grammar, $faker, 'pg-17.2');
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
        $context = new GenerationContext($grammar, $faker, 'pg-17.2');
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
        $context = new GenerationContext($grammar, $faker, 'pg-17.2');
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
        $context = new GenerationContext($grammar, $faker, 'pg-17.2');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('Unknown grammar rule: missing');
        $generator->generate(GenerationPlan::fromRule('missing'));
    }
}
