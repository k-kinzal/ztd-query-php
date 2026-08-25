<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Grammar;

use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\SqlVersion;

/**
 * PostgreSQL grammar loader.
 *
 * Loads pre-compiled grammar from the ast/pg-*.php cache files.
 */
final class PgGrammar
{
    /**
     * Load a pre-compiled PostgreSQL grammar.
     *
     * @param string|null $version PostgreSQL version tag (e.g., "pg-17.2"). Null for default.
     */
    public static function load(?string $version = null): Grammar
    {
        $path = SqlVersion::resolve('postgresql', $version)->astPath;

        return Grammar::loadFromFile($path);
    }

    /**
     * Answers the release a version string names, defaulting to the newest one shipped.
     *
     * @param string|null $version Release to resolve, or null for the default
     *
     * @return string Name of the release the artifacts were generated for
     *
     * @throws RuntimeException When the release is not one this package ships
     */
    public static function resolveVersion(?string $version = null): string
    {
        return SqlVersion::resolve('postgresql', $version)->name;
    }
}
