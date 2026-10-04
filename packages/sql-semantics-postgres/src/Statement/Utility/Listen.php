<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `LISTEN channel`: a request to receive the notifications of a channel.
 *
 * Rule: PG-LISTEN-001. Mirrors PostgreSQL's `ListenStmt`. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-listen.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the channel listened to
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('LISTEN Jobs')->statement->channel->value // => 'jobs'
 */
final class Listen implements Statement
{
    use Snapshot;

    /**
     * @param Name $channel The channel name
     */
    public function __construct(public readonly Name $channel)
    {
    }

    /**
     * Derives nothing: a channel is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('LISTEN')->name($this->channel, NameUse::Column);
    }
}
