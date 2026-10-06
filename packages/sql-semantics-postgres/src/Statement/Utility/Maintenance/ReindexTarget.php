<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

/**
 * What REINDEX rebuilds the indexes of.
 *
 * Mirrors PostgreSQL's `ReindexObjectType`. The value is the keyword.
 * Source: https://www.postgresql.org/docs/17/sql-reindex.html.
 *
 * @visibility public
 * @example Telling which targets are named by a relation name
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget::Table->relation(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget::Schema->relation()] // => [true, false]
 */
enum ReindexTarget: string
{
    case Index = 'INDEX';
    case Table = 'TABLE';
    case Schema = 'SCHEMA';
    case System = 'SYSTEM';
    case Database = 'DATABASE';

    /**
     * Tells whether the target is named by a possibly schema-qualified relation name.
     */
    public function relation(): bool
    {
        return $this === self::Index || $this === self::Table;
    }
}
