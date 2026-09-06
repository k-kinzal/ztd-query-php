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
#[UsesClass(\SqlFaker\MySql\ParserSemantics::class)]
#[UsesClass(\SqlFaker\Generation\SqlGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalCatalog::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalCatalogException::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalCatalogShape::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalCoverageCheck::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalKeywordIndex::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalProfileSource::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalWitnessCheck::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\LexicalWitnessShape::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomCharacters::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\RandomStringGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\TerminalInventory::class)]
#[UsesClass(\SqlFaker\Grammar\Lexical\TokenJoiner::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\MySql\GenerationContext::class)]
#[UsesClass(\SqlFaker\MySql\Grammar\MySqlGrammar::class)]
#[UsesClass(\SqlFaker\MySql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTerminalRealizer::class)]
#[UsesClass(\SqlFaker\MySql\MySqlTokenizer::class)]
#[UsesClass(\SqlFaker\MySql\StartRuleResolver::class)]
#[UsesClass(\SqlFaker\PostgreSql\GenerationContext::class)]
#[UsesClass(\SqlFaker\PostgreSql\Grammar\PgGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\PostgreSql\ParserSemantics::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgTerminalRealizer::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgTokenizer::class)]
#[UsesClass(\SqlFaker\Sqlite\GenerationContext::class)]
#[UsesClass(\SqlFaker\Sqlite\GrammarAdaptation::class)]
#[UsesClass(\SqlFaker\Sqlite\Grammar\SqliteGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\LexicalGrammar::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTerminalRealizer::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteTokenizer::class)]
final class SqlGeneratorFactoryTest extends TestCase
{
    public function testForMySqlBindsTheDialectEntryPointAndLexicalGrammar(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'statement' => new ProductionRule('statement', [new Production([new Terminal('SELECT_SYM')])]),
        ]);
        $generator = SqlGeneratorFactory::forMySql($faker, $grammar, 'mysql-8.4.7');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }

    public function testForPostgreSqlBindsTheDialectEntryPointAndLexicalGrammar(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'stmtmulti' => new ProductionRule('stmtmulti', [new Production([new Terminal('SELECT')])]),
        ]);
        $generator = SqlGeneratorFactory::forPostgreSql($faker, $grammar, 'pg-17.2');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }

    public function testForSqliteBindsTheDialectEntryPointAndLexicalGrammar(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $grammar = new Grammar('entry', [
            'cmd' => new ProductionRule('cmd', [new Production([new Terminal('SELECT')])]),
        ]);
        $generator = SqlGeneratorFactory::forSqlite($faker, $grammar, 'sqlite-3.47.2');

        self::assertMatchesRegularExpression('/SELECT/i', $generator->generate(GenerationPlan::all()));
        self::assertSame('7', $generator->generate(GenerationPlan::lexical('integer_literal', ['min' => 7, 'max' => 7])));
    }
}
