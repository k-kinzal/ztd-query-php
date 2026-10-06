<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A sequence option without an operand: CYCLE, NO CYCLE, NO MINVALUE, NO MAXVALUE, RESTART, LOGGED or UNLOGGED.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html, https://www.postgresql.org/docs/17/sql-altersequence.html.
 *
 * @visibility public
 * @example Reading a flag
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SEQUENCE s NO CYCLE');
 *     $create->statement->options[0]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind::NoCycle
 */
final class SequenceFlag implements SequenceOption
{
    use Snapshot;

    /**
     * @param SequenceFlagKind $kind The option
     */
    public function __construct(public readonly SequenceFlagKind $kind)
    {
    }

    /**
     * Derives nothing: the option has no operand.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value));
    }
}
