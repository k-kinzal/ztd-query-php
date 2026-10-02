<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The result column `t.*`: every column of the input relations a name denotes.
 *
 * @visibility public
 * @example Reading the relation a qualified star names
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT u.* FROM t JOIN u');
 *     $query->statement->columns[0]->table->value // => 'u'
 */
final class TableStar implements Node
{
    use Snapshot;

    /**
     * @param Name $table The correlation or table name
     */
    public function __construct(public readonly Name $table)
    {
    }

    /**
     * Writes the name and the star.
     */
    public function render(Output $out): void
    {
        $out->name($this->table, NameUse::Qualifier)->symbol('.')->symbol('*');
    }
}
