<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The result column `t.*`: every column of the input relations a name denotes.
 *
 * @visibility SqlSemantics
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
