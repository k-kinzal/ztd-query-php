<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The index a table use demands: a named index, or no index at all.
 *
 * Source: https://sqlite.org/lang_indexedby.html.
 *
 * @visibility public
 * @example Reading the demanded index
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t INDEXED BY i');
 *     $query->statement->from->index->index->value // => 'i'
 */
final class IndexChoice implements Node
{
    use Snapshot;

    /**
     * @param Name|null $index The index of INDEXED BY; null for NOT INDEXED
     */
    public function __construct(public readonly ?Name $index = null)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        if ($this->index === null) {
            $out->keyword('NOT', 'INDEXED');
        } else {
            $out->keyword('INDEXED', 'BY')->name($this->index, NameUse::Label);
        }
    }
}
