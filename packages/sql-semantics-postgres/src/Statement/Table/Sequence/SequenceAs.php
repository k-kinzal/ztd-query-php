<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

/**
 * The AS option of a sequence: the data type of its values.
 *
 * The server accepts smallint, integer and bigint.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html.
 *
 * @visibility SqlSemantics
 */
final class SequenceAs implements SequenceOption
{
    use Snapshot;

    /**
     * @param TypeDesignation $type The data type
     */
    public function __construct(public readonly TypeDesignation $type)
    {
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes AS and the type.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->node($this->type);
    }
}
