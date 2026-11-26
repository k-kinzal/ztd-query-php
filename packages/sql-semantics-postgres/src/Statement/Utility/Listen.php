<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * `LISTEN channel`: a request to receive the notifications of a channel.
 *
 * Rule: PG-LISTEN-001. Mirrors PostgreSQL's `ListenStmt`. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-listen.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
