<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Sqlite TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    /**
     * Retains the language identity used in semantic output.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type, including table-dependent storage rules and modifiers.
     */
    public function read(Node $node, ?Node $table = null): TypeDescriptor
    {
        $tokens = $node->tokens();
        $words = [];
        $modifiers = [];
        $inModifiers = false;
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                $inModifiers = true;
            } elseif ($token->text === ')') {
                $inModifiers = false;
            } elseif ($token->text !== ',') {
                if ($inModifiers) {
                    $modifiers[] = $token->text;
                } else {
                    $words[] = strtoupper((new NameRules())->name($token));
                }
            }
        }
        $name = implode(' ', $words);
        $affinity = $this->affinity($name);
        if ($name === 'ANY' && $table !== null) {
            foreach (Tree::outer($table, ['table_option']) as $option) {
                if (strtoupper(Tree::text($option)) === 'STRICT') {
                    $affinity = 'blob';
                }
            }
        }
        return new TypeDescriptor($this->dialect, strtolower($name), $modifiers, $affinity);
    }

    /**
     * Resolves the built-in aliases modeled for this dialect.
     */
    public function canonical(string $name): ?string
    {
        return match ($name) {
            'INT', 'INTEGER', 'INT4' => 'integer',
            'SMALLINT', 'INT2' => 'smallint',
            'BIGINT', 'INT8' => 'bigint',
            'DEC', 'DECIMAL', 'NUMERIC' => 'numeric',
            'REAL' => 'real',
            'FLOAT4' => 'real',
            'DOUBLE', 'DOUBLE PRECISION', 'FLOAT8' => 'double precision',
            'BOOL', 'BOOLEAN' => 'boolean',
            'VARCHAR', 'CHARACTER VARYING', 'CHAR VARYING' => 'varchar',
            'CHAR', 'CHARACTER' => 'char',
            'TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'JSON' => strtolower($name),
            'TINYINT', 'MEDIUMINT', 'DATETIME', 'BLOB' => null,
            'UUID', 'BYTEA', 'JSONB', 'TIMESTAMPTZ', 'TIMETZ', 'INTERVAL' => null,
            default => null,
        };
    }

    /**
     * Computes storage affinity from a declaration name.
     */
    public function affinity(string $name): string
    {
        if (str_contains($name, 'INT')) {
            return 'integer';
        }
        if (str_contains($name, 'CHAR') || str_contains($name, 'CLOB') || str_contains($name, 'TEXT')) {
            return 'text';
        }
        if ($name === '' || str_contains($name, 'BLOB')) {
            return 'blob';
        }
        if (str_contains($name, 'REAL') || str_contains($name, 'FLOA') || str_contains($name, 'DOUB')) {
            return 'real';
        }
        return 'numeric';
    }
}
