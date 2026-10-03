<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Type\SqliteDeclaration;

/**
 * Decodes the declared type name exactly as SQLite stores it, without retaining grammar objects.
 * @visibility SqlSemantics
 */
final class DeclaredTypeReader
{
    /**
     * Mirrors native-name recognition followed by stored-name decoding in sqlite3AddColumn.
     * Internal whitespace, comments, and size annotations can affect the database type identity.
     */
    public function read(Node $source, bool $strict = false): SqliteDeclaration
    {
        assert($source->name === 'typetoken', 'A declared type reader receives the type position.');
        $tokens = $source->tokens();
        if ($tokens === []) {
            return new SqliteDeclaration(strict: $strict);
        }
        $span = substr($source->toString(), strlen($tokens[0]->leading));
        if (strlen($span) >= 16 && strcasecmp(substr($span, -6), 'always') === 0) {
            $span = rtrim(substr($span, 0, -6));
            if (strlen($span) >= 9 && strcasecmp(substr($span, -9), 'generated') === 0) {
                $span = rtrim(substr($span, 0, -9));
            }
        }
        $optimized = strlen($span) >= 3 && str_contains("\"'`[", $span[0]) && strpbrk(substr($span, 1, -1), "\"'`[") === false;
        if ($optimized) {
            $span = substr($span, 1, -1);
        }
        $native = in_array(strtoupper($span), ['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'], true);
        $first = (new IdentifierReader())->name($tokens[0]);
        $name = !$optimized && $first->quote !== Quote::None ? $first->value : $span;
        return new SqliteDeclaration($name, $strict, !$native);
    }
}
