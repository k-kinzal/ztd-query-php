<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;

/**
 * A sequence option with a number: CACHE, INCREMENT, MAXVALUE, MINVALUE, START or RESTART.
 *
 * The optional BY after INCREMENT and WITH after START and RESTART change
 * nothing ("INCREMENT [ BY ] increment", "START [ WITH ] start") and are not
 * kept.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html, https://www.postgresql.org/docs/17/sql-altersequence.html.
 *
 * @visibility SqlSemantics
 */
final class SequenceNumber implements SequenceOption
{
    use Snapshot;

    /**
     * @param SequenceNumberKind $kind The option
     * @param SignedNumber $value The number
     */
    public function __construct(public readonly SequenceNumberKind $kind, public readonly SignedNumber $value)
    {
    }

    /**
     * Derives nothing: the number is a constant.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keyword and the number.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->value);
    }
}
