<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Grammar;

use RuntimeException;
use SqlFaker\Grammar\Grammar;

/**
 * PostgreSQL grammar loader.
 *
 * Loads pre-compiled grammar from the ast/pg-*.php cache files.
 */
final class PgGrammar
{
    private const AST_DIR = __DIR__ . '/../../../resources/ast';
    private const AST_META = __DIR__ . '/../../../resources/ast.php';

    /**
     * Load a pre-compiled PostgreSQL grammar.
     *
     * @param string|null $version PostgreSQL version tag (e.g., "pg-17.2"). Null for default.
     */
    public static function load(?string $version = null): Grammar
    {
        if ($version === null) {
            /** @var array{default_pg?: string} $meta */
            $meta = require self::AST_META;
            $version = $meta['default_pg'] ?? null;
            if ($version === null) {
                throw new RuntimeException('No default PostgreSQL version configured in ast.php');
            }
        }

        $path = self::AST_DIR . '/' . $version . '.php';

        return Grammar::loadFromFile($path);
    }
}
