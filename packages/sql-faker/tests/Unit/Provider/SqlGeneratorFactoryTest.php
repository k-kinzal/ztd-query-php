<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Provider;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Provider\SqlGeneratorFactory;

#[CoversClass(SqlGeneratorFactory::class)]
#[UsesClass(\SqlFaker\Generation\SqlGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomStringGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\TerminalInventory::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\MySql\GenerationContext::class)]
#[UsesClass(\SqlFaker\MySql\Grammar\MySqlGrammar::class)]
#[UsesClass(\SqlFaker\MySql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\StartRuleResolver::class)]
#[UsesClass(\SqlFaker\PostgreSql\GenerationContext::class)]
#[UsesClass(\SqlFaker\PostgreSql\Grammar\PgGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgTokenizer::class)]
#[UsesClass(\SqlFaker\Sqlite\GenerationContext::class)]
#[UsesClass(\SqlFaker\Sqlite\GrammarAdaptation::class)]
#[UsesClass(\SqlFaker\Sqlite\Grammar\SqliteGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTokenizer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator::class)]
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
#[UsesClass(\SqlFaker\Grammar\Generation\Token\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
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
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
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
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\GenerationException::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule::class)]
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
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\ConstraintCapabilitiesRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\ForeignKeyActionRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\SchemaElementsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Query\ParserOptionsRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\JsonTablePathRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Routine\AggregateArgumentRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ConcatenationRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\HostNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Name\ParserNameRule::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Rewrite\Column\NumericContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RepeatDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\OperatorDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\Utf8::class)]
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
