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
 * `UNLISTEN channel` or `UNLISTEN *`: a request to stop receiving notifications.
 *
 * Rule: PG-UNLISTEN-001. Mirrors PostgreSQL's `UnlistenStmt`, whose
 * `conditionname` is null for every channel. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-unlisten.html. Status: Implemented.
 *
 * @visibility public
 * @example Leaving every channel
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('UNLISTEN *');
 *     [$operation->statement->channel, $operation->toString()] // => [null, 'UNLISTEN *']
 */
final class Unlisten implements Statement
{
    use Snapshot;

    /**
     * @param Name|null $channel The channel name; null for every channel
     */
    public function __construct(public readonly ?Name $channel = null)
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
        $out->keyword('UNLISTEN');
        if ($this->channel === null) {
            $out->symbol('*');

            return;
        }
        $out->name($this->channel, NameUse::Column);
    }
}
