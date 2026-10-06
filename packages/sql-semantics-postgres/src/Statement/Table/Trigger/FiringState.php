<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

/**
 * Whether a trigger or rule fires, and in which session replication role.
 *
 * Mirrors the `tgenabled`/`evtenabled` codes: ENABLE fires in origin and local mode, ENABLE REPLICA in replica mode, ENABLE ALWAYS in every mode, DISABLE never.
 * Source: https://www.postgresql.org/docs/17/sql-altereventtrigger.html.
 *
 * @visibility public
 * @example Reading the firing state of an event trigger
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EVENT TRIGGER e ENABLE ALWAYS')->statement->state // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\FiringState::EnableAlways
 */
enum FiringState: string
{
    case Enable = 'ENABLE';
    case EnableReplica = 'ENABLE REPLICA';
    case EnableAlways = 'ENABLE ALWAYS';
    case Disable = 'DISABLE';

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
