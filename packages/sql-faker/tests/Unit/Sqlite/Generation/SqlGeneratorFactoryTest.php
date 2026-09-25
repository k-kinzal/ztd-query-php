<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Sqlite\Generation\SqlGeneratorFactory;

#[CoversClass(SqlGeneratorFactory::class)]
#[UsesClass(\SqlFaker\Generation\SqlGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Derivation::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\Generation\Value\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Model\TerminalInventory::class)]
#[UsesClass(\SqlFaker\Grammar\Model\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersion::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\GenerationContext::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\GrammarAdaptation::class)]
#[UsesClass(\SqlFaker\Sqlite\Grammar\SqliteGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Tokenization\SqliteTokenizer::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\OutputPart::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Generation\Output\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionCase::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\IdentifierListRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\JoinRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\StrictTableRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\TableOptionRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\WithoutRowidRule::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Exception\GenerationException::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Generation\Exception\LexicalException::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\CompoundSelectRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\UpsertSourceRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\FunctionArgumentRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\RepeatDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\CompletionWitness::class)]
#[UsesClass(\SqlFaker\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\LexicalDefinition::class)]
final class SqlGeneratorFactoryTest extends TestCase
{
    public function testCreatePreservesTheGrammarEntryPointAndBindsLexicalDefinitions(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])]),
        ]);
        $generator = SqlGeneratorFactory::create($faker, $grammar, 'sqlite-3.47.2');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }
}
