<?php

declare(strict_types=1);

namespace SqlFaker\Provider;

use Faker\Generator;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\MySql\Generation\GenerationContext as MySqlContext;
use SqlFaker\PostgreSql\Generation\GenerationContext as PgContext;
use SqlFaker\Sqlite\Generation\GenerationContext as SqliteContext;

/**
 * Assembles the dialect collaborators used by the Faker providers.
 *
 * @visibility root
 */
final class SqlGeneratorFactory
{
    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forMySql(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        $grammar = $grammar->identified();
        $context = new MySqlContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $coverage,
            $grammar,
        );
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forPostgreSql(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        $grammar = $grammar->identified();
        $context = new PgContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $coverage,
            $grammar,
        );
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forSqlite(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        $grammar = $grammar->identified();
        $context = new SqliteContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $coverage,
            $grammar,
        );
    }
}
