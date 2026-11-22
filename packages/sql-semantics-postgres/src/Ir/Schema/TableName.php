<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The identity of an ordinary table in a named persistent schema.
 *
 * @visibility public
 * @example Quoting both parts of a table identity
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('accounts')))->quoted() // => '"app"."accounts"'
 */
final class TableName
{
    use Snapshot;

    /**
     * Fixes the absolute schema and table names.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $table)
    {
        Check::input($schema->value !== 'pg_temp', 'A persistent PostgreSQL relation requires an absolute schema.');
    }

    /**
     * Emits both identifier positions.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->table->quoted();
    }
}
