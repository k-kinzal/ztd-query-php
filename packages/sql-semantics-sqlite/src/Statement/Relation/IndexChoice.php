<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The index a table use demands: a named index, or no index at all.
 *
 * Source: https://sqlite.org/lang_indexedby.html.
 *
 * @visibility SqlSemantics
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
