<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Filter;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One `(from_db, to_db)` pair of REPLICATE_REWRITE_DB: events of the first database are applied to the second.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html.
 *
 * @visibility public
 * @example Holding both database names
 *     $pair = new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\Name('b'));
 *     [$pair->from->value, $pair->to->value] // => ['a', 'b']
 */
final class DatabaseRewrite implements Node
{
    use Snapshot;

    /**
     * @param Name $from The database of the source
     * @param Name $to The database on the replica
     */
    public function __construct(public readonly Name $from, public readonly Name $to)
    {
    }

    /**
     * Writes the pair in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->name($this->from, NameUse::Qualifier)->symbol(',')->name($this->to, NameUse::Qualifier)->symbol(')');
    }
}
