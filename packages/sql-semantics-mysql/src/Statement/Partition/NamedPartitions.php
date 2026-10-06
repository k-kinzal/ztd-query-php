<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A comma-separated list of partition names.
 *
 * The names are not resolved: a declaration context holds no partitions.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 *
 * @visibility public
 * @example Selecting two partitions
 *     $partitions = new \SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions([new \SqlSemantics\Statement\Identifier\Name('p0'), new \SqlSemantics\Statement\Identifier\Name('p1')]);
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
