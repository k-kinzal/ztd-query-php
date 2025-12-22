<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Grammar;

use RuntimeException;
use SqlFaker\Grammar\Grammar;

/**
 * SQLite grammar loader.
 *
 * Loads pre-compiled grammar from the ast/sqlite-*.php cache files.
 */
final class SqliteGrammar
{
    private const AST_DIR = __DIR__ . '/../../../resources/ast';
    private const AST_META = __DIR__ . '/../../../resources/ast.php';

    /**
     * Load a pre-compiled SQLite grammar.
     *
     * @param string|null $version SQLite version tag (e.g., "sqlite-3.47.2"). Null for default.
     */
    public static function load(?string $version = null): Grammar
    {
        if ($version === null) {
            /** @var array{default_sqlite?: string} $meta */
            $meta = require self::AST_META;
            $version = $meta['default_sqlite'] ?? null;
            if ($version === null) {
                throw new RuntimeException('No default SQLite version configured in ast.php');
            }
        }

        $path = self::AST_DIR . '/' . $version . '.php';

        return Grammar::loadFromFile($path);
    }
}
