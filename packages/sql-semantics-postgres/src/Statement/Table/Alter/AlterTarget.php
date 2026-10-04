<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

/**
 * The kind of relation an ALTER command built on `AlterTableStmt` names.
 *
 * Mirrors the `objtype` of `AlterTableStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the kind of altered relation
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER MATERIALIZED VIEW m SET TABLESPACE s')->statement->target // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget::MaterializedView
 */
enum AlterTarget: string
{
    case Table = 'TABLE';
    case Index = 'INDEX';
    case Sequence = 'SEQUENCE';
    case View = 'VIEW';
    case MaterializedView = 'MATERIALIZED VIEW';
    case ForeignTable = 'FOREIGN TABLE';

    /**
     * Answers the keywords.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
