<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Grammar;

use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\SqlVersion;

/**
 * SQLite grammar loader.
 *
 * Loads pre-compiled grammar from the ast/sqlite-*.php cache files.
 */
final class SqliteGrammar
{
    /**
     * Load a pre-compiled SQLite grammar.
     *
     * @param string|null $version SQLite version tag (e.g., "sqlite-3.47.2"). Null for default.
     */
    public static function load(?string $version = null): Grammar
    {
        $path = SqlVersion::resolve('sqlite', $version)->astPath;

        return Grammar::loadFromFile($path);
    }

    public static function resolveVersion(?string $version = null): string
    {
        return SqlVersion::resolve('sqlite', $version)->name;
    }
}
