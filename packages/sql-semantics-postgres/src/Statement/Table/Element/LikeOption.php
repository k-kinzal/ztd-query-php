<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One INCLUDING or EXCLUDING option of LIKE.
 *
 * The options apply in the order written; a later one overrides an earlier
 * one for the properties they share.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class LikeOption implements Node
{
    use Snapshot;

    /**
     * @param bool $including Whether the option is INCLUDING; EXCLUDING otherwise
     * @param LikeOptionKind $kind The property
     */
    public function __construct(public readonly bool $including, public readonly LikeOptionKind $kind)
    {
    }

    /**
     * Writes INCLUDING or EXCLUDING and the property.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->including ? 'INCLUDING' : 'EXCLUDING', $this->kind->value);
    }
}
