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
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Reads the declared built-in type and preserves its modifiers.
     */
    public function read(Node $node): TypeDescriptor
    {
        return (new Type\DeclarationReader($this))->read($node);
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
            default => $this->dialect === Dialect::PostgreSql ? self::postgresqlAlias($name) : null,
        };
    }

    /**
     * Resolves PostgreSQL spelling aliases to the storage family selected by its grammar.
     */
    public static function postgresqlAlias(string $name): ?string
    {
        return match ($name) {
            'BIT VARYING' => 'varbit',
            'NATIONAL CHARACTER', 'NATIONAL CHAR', 'NCHAR' => 'char',
            'NATIONAL CHARACTER VARYING', 'NATIONAL CHAR VARYING', 'NCHAR VARYING' => 'varchar',
            'UUID', 'BYTEA', 'JSONB', 'TIMESTAMPTZ', 'TIMETZ', 'INTERVAL', 'BPCHAR' => strtolower($name),
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
