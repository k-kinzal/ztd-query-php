<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Interprets a supported built-in type declaration without erasing its modifiers.
 *
 * @visibility SqlSemantics
 */
final class TypeReader
{
    /**
     * Binds the dependencies used for this analysis.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Reads the declared built-in type and preserves its modifiers.
     */
    public function read(Node $node): TypeDescriptor
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
                    $words[] = strtoupper($token->text);
                }
            }
        }
        $name = implode(' ', $words);
        if ($this->dialect === Dialect::Sqlite) {
            return new TypeDescriptor($this->dialect, strtolower($name), $modifiers, $this->affinity($name));
        }
        $canonical = $this->canonical($name);
        if ($canonical === null) {
            Tree::unsupported($node, 'type declaration');
        }

        return new TypeDescriptor($this->dialect, $canonical, $modifiers);
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
            'REAL' => $this->dialect === Dialect::MySql ? 'double precision' : 'real',
            'FLOAT4' => 'real',
            'DOUBLE', 'DOUBLE PRECISION', 'FLOAT8' => 'double precision',
            'BOOL', 'BOOLEAN' => $this->dialect === Dialect::MySql ? 'tinyint' : 'boolean',
            'VARCHAR', 'CHARACTER VARYING', 'CHAR VARYING' => 'varchar',
            'CHAR', 'CHARACTER' => 'char',
            'TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'JSON' => strtolower($name),
            'TINYINT', 'MEDIUMINT', 'DATETIME', 'BLOB' => $this->dialect === Dialect::MySql ? strtolower($name) : null,
            'UUID', 'BYTEA', 'JSONB', 'TIMESTAMPTZ', 'TIMETZ', 'INTERVAL' => $this->dialect === Dialect::PostgreSql ? strtolower($name) : null,
            default => null,
        };
    }

    /**
     * Computes SQLite affinity in the documented precedence order.
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
