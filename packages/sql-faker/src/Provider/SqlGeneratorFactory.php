<?php

declare(strict_types=1);

namespace SqlFaker\Provider;

use Faker\Generator;
use SqlFaker\Generation\SqlGenerator;
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
    public static function forMySql(Generator $faker, Grammar $grammar, string $version): SqlGenerator
    {
        $context = new MySqlContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->normalize,
            $context->startSymbol,
        );
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forPostgreSql(Generator $faker, Grammar $grammar, string $version): SqlGenerator
    {
        $context = new PgContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->normalize,
            $context->startSymbol,
        );
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forSqlite(Generator $faker, Grammar $grammar, string $version): SqlGenerator
    {
        $context = new SqliteContext($grammar, $faker, $version);

        return new SqlGenerator(
            $context->grammar,
            $faker,
            $context->lexicalGrammar,
            $context->normalize,
            $context->startSymbol,
        );
    }
}
