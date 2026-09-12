<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\GenerationContext;

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
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Derivation::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ValueLexemeGenerator::class)]
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
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Option\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionCase::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\Generation\Value\RandomCharacters::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Model\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersion::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DefinitionFactory::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterDatabaseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\ConstraintEnforcementRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\FlushExportRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\InstanceActionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntegerContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\LoadSourceCountRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RequiredAliasRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RewriteDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RoleGrantRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SetNamesRule::class)]
#[UsesClass(\SqlFaker\MySql\Grammar\MySqlGrammar::class)]
#[UsesClass(\SqlFaker\MySql\Generation\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Tokenization\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\Generation\StartRuleResolver::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterEventRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SubqueryContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\TransactionCompletionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\StartRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\TableValueConstructorRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\QueryContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\JoinGroupingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Column\FieldLengthRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Routine\ReturnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\SystemVariableRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Column\AutoIncrementRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\DefinitionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ListValueRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Routine\LanguageRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueArityRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueShape::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ConcatenationRule::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\HostNameRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\CompletionWitness::class)]
#[UsesClass(\SqlFaker\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\LexicalDefinition::class)]
final class GenerationContextTest extends TestCase
{
    public function testBindsTheRequestedReleaseAndPreservesTheGrammarStart(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT_SYM')])])]);
        $context = new GenerationContext($grammar, Factory::create(), 'mysql-8.4.7');
        self::assertSame('mysql-8.4.7', $context->lexicalGrammar->version());
        self::assertSame('entry', $context->grammar->startSymbol);
        self::assertSame($grammar->ruleMap['entry'], $context->grammar->ruleMap['entry']);
    }

    public function testUsesTheGrammarStartWhenThePlanDoesNotSpecifyOne(): void
    {
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT_SYM')])]),
            'fragment' => new ProductionRule('fragment', [new Production([])]),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'mysql-8.4.7');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()));
        self::assertSame('', $generator->generate(GenerationPlan::fromRule('fragment')));
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()));
    }

    public function testLeavesThePlanInControlOfNullableProductionChoices(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [
            new Production([]), new Production([new Terminal('SELECT_SYM')]),
        ])]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'mysql-8.4.7');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()->requiringNonEmpty()->withMaxDepth(1)));
        self::assertSame('', $generator->generate(GenerationPlan::constrained('entry', ['entry' => [ProductionPattern::exactly()]])));
    }

    public function testReportsMissingLexicalImplementationWhenTheTerminalIsActuallyGenerated(): void
    {
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT_SYM')])]),
            'missing' => new ProductionRule('missing', [new Production([new Terminal('UNIMPLEMENTED')])]),
        ]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'mysql-8.4.7');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        self::assertSame('SELECT', $generator->generate(GenerationPlan::all()));
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('UNIMPLEMENTED');
        $generator->generate(GenerationPlan::fromRule('missing'));
    }

    public function testReportsAnUnknownNonterminalWithoutTreatingItAsSqlText(): void
    {
        $grammar = new Grammar('entry', ['entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT_SYM')])])]);
        $faker = Factory::create();
        $context = new GenerationContext($grammar, $faker, 'mysql-8.4.7');
        $generator = new SqlGenerator($context->grammar, $faker, $context->lexicalGrammar, $context->rewriter, $context->startSymbol);
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage('Unknown grammar rule: missing');
        $generator->generate(GenerationPlan::fromRule('missing'));
    }
}
