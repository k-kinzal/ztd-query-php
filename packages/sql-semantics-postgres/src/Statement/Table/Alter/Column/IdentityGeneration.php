<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen;

/**
 * SET GENERATED { ALWAYS | BY DEFAULT } of an identity column.
 *
 * Mirrors the `generated` `DefElem` of `AT_SetIdentity`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
