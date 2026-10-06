<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

/**
 * What identifies a row in logical replication.
 *
 * Mirrors `REPLICA_IDENTITY_NOTHING`, `FULL`, `DEFAULT` and `INDEX`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the replica identity kind
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t REPLICA IDENTITY FULL')->statement->commands[0]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentityKind::Full
 */
enum ReplicaIdentityKind: string
{
    case Nothing = 'NOTHING';
    case Full = 'FULL';
    case Default = 'DEFAULT';
    case Index = 'USING INDEX';

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
