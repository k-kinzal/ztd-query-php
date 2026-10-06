<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\KeyCache;

use SqlSemantics\Platform\MySql\Rules\Server\CachedIndexes;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One table whose indexes CACHE INDEX assigns to a key cache: `t [PARTITION (…)] [INDEX (…)]`.
 *
 * Mirrors PT_assign_to_keycache. The statement that holds it records the
 * resolution of the table name as the relation fact of this node. An absent
 * index list assigns every index of the table, an empty list none
 * (MYSQL-CACHED-INDEXES-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cache-index.html.
 *
 * @visibility public
 * @example Holding a table and its index list
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), null, [new \SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex()]);
 *     count($table->indexes ?? []) // => 1
 */
final class CachedTable implements Node
{
    use Snapshot;

    /**
     * @var list<Name|PrimaryIndex>|null The indexes, when an index list is written
     */
    public readonly ?array $indexes;

    /**
     * @param QualifiedName $table The table name with its optional database
     * @param PartitionSelection|null $partitions The partitions, when written
     * @param list<Name|PrimaryIndex>|null $indexes The indexes, when an index list is written
     */
    public function __construct(public readonly QualifiedName $table, public readonly ?PartitionSelection $partitions = null, ?array $indexes = null)
    {
        $this->indexes = (new CachedIndexes())->checked($table, $indexes);
    }

    /**
     * Writes the table, the partitions and the index list.
     */
    public function render(Output $out): void
    {
        (new CachedIndexes())->render($out, $this->table, $this->partitions, $this->indexes);
    }
}
