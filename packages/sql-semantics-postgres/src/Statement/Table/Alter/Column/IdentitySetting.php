<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption;

/**
 * SET with a sequence option of an identity column.
 *
 * Mirrors the `DefElem` a `SET SeqOptElem` adds to `AT_SetIdentity`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Setting a sequence option of an identity
 *     $statement = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('ALTER TABLE t ALTER a SET CYCLE');
 *     $statement->statement->commands[0]->options[0]->option->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind::Cycle
 */
final class IdentitySetting implements Node
{
    use Snapshot;

    /**
     * @param SequenceOption $option The option
     */
    public function __construct(public readonly SequenceOption $option)
    {
    }

    /**
     * Writes SET and the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET')->node($this->option);
    }
}
