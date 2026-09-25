<?php

declare(strict_types=1);

namespace SqlFaker\Compatibility;

use Faker\Generator;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\MySql\Generation\SqlGeneratorFactory as MySqlFactory;
use SqlFaker\PostgreSql\Generation\SqlGeneratorFactory as PgFactory;
use SqlFaker\Sqlite\Generation\SqlGeneratorFactory as SqliteFactory;

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
        return MySqlFactory::create($faker, $grammar, $version, $coverage);
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forPostgreSql(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        return PgFactory::create($faker, $grammar, $version, $coverage);
    }

    /**
     * Binds the release's grammar and lexical behavior to a SQL generator.
     */
    public static function forSqlite(Generator $faker, Grammar $grammar, string $version, ?GrammarCoverage $coverage = null): SqlGenerator
    {
        return SqliteFactory::create($faker, $grammar, $version, $coverage);
    }
}
