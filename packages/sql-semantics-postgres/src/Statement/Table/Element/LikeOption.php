<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One INCLUDING or EXCLUDING option of LIKE.
 *
 * The options apply in the order written; a later one overrides an earlier
 * one for the properties they share.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading whether an option includes or excludes
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (LIKE u EXCLUDING DEFAULTS)');
 *     $create->statement->definition->elements[0]->options[0]->including // => false
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
