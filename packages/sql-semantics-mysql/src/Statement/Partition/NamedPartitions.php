<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A comma-separated list of partition names.
 *
 * The names are not resolved: a declaration context holds no partitions.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility SqlSemantics
 * @example Selecting two partitions
 *     $partitions = new \SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions([new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('p0'), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('p1')]);
 *     count($partitions->names) // => 2
 */
final class NamedPartitions implements PartitionSelection
{
    use Snapshot;

    /**
     * @var list<Name> The partition names in order; at least one
     */
    public readonly array $names;

    /**
     * @param list<Name> $names The partition names in order; at least one
     */
    public function __construct(array $names)
    {
        Check::input($names !== [], 'A partition list names at least one partition.');
        $this->names = Check::listOf($names, Name::class, 'Partition names are a list of names.');
    }

    /**
     * Writes the names.
     */
    public function render(Output $out): void
    {
        foreach ($this->names as $index => $name) {
            if ($index > 0) {
                $out->symbol(',');
            }
            $out->name($name, NameUse::Label);
        }
    }
}
