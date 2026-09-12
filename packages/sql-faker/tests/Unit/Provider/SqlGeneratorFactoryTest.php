<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Provider;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Provider\SqlGeneratorFactory;

#[CoversClass(SqlGeneratorFactory::class)]
#[UsesClass(\SqlFaker\Generation\SqlGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Derivation::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Tokenization\KeywordIndex::class)]
#[UsesClass(\SqlFaker\Generation\Value\RandomCharacters::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\LiteralGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Model\TerminalInventory::class)]
#[UsesClass(\SqlFaker\Grammar\Model\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersion::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\MySql\Generation\GenerationContext::class)]
#[UsesClass(\SqlFaker\MySql\Grammar\MySqlGrammar::class)]
#[UsesClass(\SqlFaker\MySql\Generation\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Tokenization\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\Generation\StartRuleResolver::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\GenerationContext::class)]
#[UsesClass(\SqlFaker\PostgreSql\Grammar\PgGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lookahead\PgLookahead::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Tokenization\PgTokenizer::class)]
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
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Option\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Option\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionCase::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionedLexemeGenerator::class)]
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
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Exception\GenerationException::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Generation\Exception\LexicalException::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterEventRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SubqueryContextRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\ConstraintAttributesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\AnyRelationNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\IndirectionStarRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\TransactionCompletionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\CompoundSelectRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\UpsertSourceRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\FunctionArgumentRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\StartRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SelectOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\TableFunctionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\TypeModifierRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\IdentityOptionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\TableValueConstructorRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\QueryContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\JoinGroupingRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\WithinGroupRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Column\FieldLengthRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Routine\ReturnRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\ColumnNameRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\SystemVariableRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Column\AutoIncrementRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\DefinitionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ListValueRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Routine\LanguageRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\AliasRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueArityRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueShape::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\SubstringRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\RangeFunctionOrdinalityRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonOptionsRule::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\ConstraintCapabilitiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\ForeignKeyActionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SchemaElementsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\ParserOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonTablePathRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\AggregateArgumentRule::class)]
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
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\ParserNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\NumericContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\RepeatDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\PatternProductions::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\Completion\CompletionWitness::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\OperatorDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\LexicalDefinition::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\LexicalDefinition::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\LexicalDefinition::class)]
final class SqlGeneratorFactoryTest extends TestCase
{
    public function testForMySqlPreservesTheGrammarEntryPointAndBindsLexicalDefinitions(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT_SYM')])]),
        ]);
        $generator = SqlGeneratorFactory::forMySql($faker, $grammar, 'mysql-8.4.7');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }

    public function testForPostgreSqlPreservesTheGrammarEntryPointAndBindsLexicalDefinitions(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])]),
        ]);
        $generator = SqlGeneratorFactory::forPostgreSql($faker, $grammar, 'pg-17.2');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }

    public function testForSqlitePreservesTheGrammarEntryPointAndBindsLexicalDefinitions(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'entry' => new ProductionRule('entry', [new Production([new Terminal('SELECT')])]),
        ]);
        $generator = SqlGeneratorFactory::forSqlite($faker, $grammar, 'sqlite-3.47.2');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }
}
