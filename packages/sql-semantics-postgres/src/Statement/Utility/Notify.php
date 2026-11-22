<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;

/**
 * `NOTIFY channel [, payload]`: a request to send a notification to the listeners of a channel.
 *
 * Rule: PG-NOTIFY-001. Mirrors PostgreSQL's `NotifyStmt` (conditionname,
 * payload). The statement also occurs as an action of a rule. Facts: none; a
 * channel is not part of a declaration context. Diagnostic: a payload of
 * 8000 bytes or more, the limit of the default configuration (a block size
 * of 8192 bytes), counted in UTF-8.
 * Source: https://www.postgresql.org/docs/17/sql-notify.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Notify implements Statement
{
    use Snapshot;

    /**
     * The number of bytes a payload must stay below.
     */
    private const PAYLOAD_LIMIT = 8000;

    /**
     * @param Name $channel The channel name
     * @param StringConstant|null $payload The payload string, when written
     */
    public function __construct(public readonly Name $channel, public readonly ?StringConstant $payload = null)
    {
    }

    /**
     * Reports a payload longer than the server takes.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->payload !== null && strlen($this->payload->value) >= self::PAYLOAD_LIMIT) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::PayloadTooLong));
        }
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
