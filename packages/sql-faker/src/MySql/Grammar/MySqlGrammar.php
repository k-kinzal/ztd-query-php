<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\SqlVersion;

/**
 * MySQL grammar loader.
 *
 * Loads pre-compiled grammar from the ast/mysql-*.php cache files.
 */
final class MySqlGrammar
{
    /**
     * Load a pre-compiled MySQL grammar.
     *
     * @param string|null $version MySQL version tag (e.g., "mysql-8.4.7"). Null for default.
     */
    public static function load(?string $version = null): Grammar
    {
        $path = SqlVersion::resolve('mysql', $version)->astPath;

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
        return SqlVersion::resolve('mysql', $version)->name;
    }
}
