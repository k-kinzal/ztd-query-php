<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

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
use SqlFaker\MySql\GenerationContext;

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
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
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
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomStringGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
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
#[UsesClass(\SqlFaker\MySql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\StartRuleResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule::class)]
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
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueArityRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueShape::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ConcatenationRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\HostNameRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexicalDefinition::class)]
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
