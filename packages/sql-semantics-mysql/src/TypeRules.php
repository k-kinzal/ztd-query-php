<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

/**
 * MySql TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
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
                    $words[] = $token->text;
                }
            }
        }
        $name = implode(' ', $words);
        $canonical = $this->canonical(strtoupper($name)) ?? $name;
        return new TypeDescriptor($canonical, $modifiers);
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
            'REAL' => 'double precision',
            'FLOAT4' => 'real',
            'DOUBLE', 'DOUBLE PRECISION', 'FLOAT8' => 'double precision',
            'BOOL', 'BOOLEAN' => 'tinyint',
            'VARCHAR', 'CHARACTER VARYING', 'CHAR VARYING' => 'varchar',
            'CHAR', 'CHARACTER' => 'char',
            'TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'JSON' => strtolower($name),
            'TINYINT', 'MEDIUMINT', 'DATETIME', 'BLOB' => strtolower($name),
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
