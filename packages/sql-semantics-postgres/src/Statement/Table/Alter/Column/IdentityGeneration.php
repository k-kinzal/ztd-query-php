<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * SET GENERATED { ALWAYS | BY DEFAULT } of an identity column.
 *
 * Mirrors the `generated` `DefElem` of `AT_SetIdentity`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Changing when an identity is generated
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER a SET GENERATED ALWAYS');
 *     $statement->statement->commands[0]->options[0]->when // => \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen::Always
 */
final class IdentityGeneration implements Node
{
    use Snapshot;

    /**
     * @param GeneratedWhen $when When the column takes a generated value
     */
    public function __construct(public readonly GeneratedWhen $when)
    {
    }

    /**
     * Writes SET GENERATED and when.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'GENERATED', ...$this->when->keywords());
    }
}
