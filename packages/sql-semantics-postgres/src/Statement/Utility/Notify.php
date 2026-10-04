<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `NOTIFY channel [, payload]`: a request to send a notification to the listeners of a channel.
 *
 * Rule: PG-NOTIFY-001. Mirrors PostgreSQL's `NotifyStmt` (conditionname,
 * payload). The statement also occurs as an action of a rule. Facts: none; a
 * channel is not part of a declaration context.
 * Source: https://www.postgresql.org/docs/17/sql-notify.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a notification with a payload
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("NOTIFY jobs, 'ready'");
 *     [$operation->statement->channel->value, $operation->statement->payload->value] // => ['jobs', 'ready']
 */
final class Notify implements Statement
{
    use Snapshot;

    /**
     * @param Name $channel The channel name
     * @param StringConstant|null $payload The payload string, when written
     */
    public function __construct(public readonly Name $channel, public readonly ?StringConstant $payload = null)
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
        $out->keyword('NOTIFY')->name($this->channel, NameUse::Column);
        if ($this->payload !== null) {
            $out->symbol(',')->node($this->payload);
        }
    }
}
