<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * RESTART [ [ WITH ] value ] of an identity column.
 *
 * The optional WITH changes nothing and is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Restarting an identity
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER a RESTART');
 *     $statement->statement->commands[0]->options[0]->value // => null
 */
final class IdentityRestart implements Node
{
    use Snapshot;

    /**
     * @param SignedNumber|null $value The value; null restarts at the start value
     */
    public function __construct(public readonly ?SignedNumber $value = null)
    {
    }

    /**
     * Writes RESTART and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword('RESTART')->node($this->value);
    }
}
