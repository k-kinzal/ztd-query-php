<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `SUBPARTITION name [options]`: one subpartition of a partition definition.
 *
 * Mirrors PT_subpartition. The name may be written as an identifier or a
 * string; both name the subpartition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-subpartitions.html.
 *
 * @visibility SqlSemantics
 * @example Holding a subpartition
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('s0'), []))->name->value // => 's0'
 */
final class SubpartitionDefinition implements Node
{
    use Snapshot;

    /**
     * @var list<PartitionOption> The options in order
     */
    public readonly array $options;

    /**
     * @param Name $name The subpartition name
     * @param list<PartitionOption> $options The options in order
     */
    public function __construct(public readonly Name $name, array $options)
    {
        $this->options = Check::listOf($options, PartitionOption::class, 'Subpartition options are a list of partition options.');
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('SUBPARTITION')->name($this->name, NameUse::Label);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
