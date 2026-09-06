<?php

declare(strict_types=1);

namespace SqlFaker\Provider;

use Faker\Generator;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Choice\ChoiceSource;
use SqlFaker\Grammar\Grammar;
use SqlFaker\MySql\GenerationContext as MySqlContext;
use SqlFaker\PostgreSql\GenerationContext as PgContext;
use SqlFaker\Sqlite\GenerationContext as SqliteContext;

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
        $choices = new ChoiceSource($faker);
        $grammar = $grammar->identified();
        $context = new MySqlContext($grammar, $choices, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $choices,
            $coverage,
            $grammar,
        );
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forPostgreSql(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        $choices = new ChoiceSource($faker);
        $grammar = $grammar->identified();
        $context = new PgContext($grammar, $choices, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $choices,
            $coverage,
            $grammar,
        );
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forSqlite(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        $choices = new ChoiceSource($faker);
        $grammar = $grammar->identified();
        $context = new SqliteContext($grammar, $choices, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->rewriter,
            $context->startSymbol,
            $choices,
            $coverage,
            $grammar,
        );
    }
}
