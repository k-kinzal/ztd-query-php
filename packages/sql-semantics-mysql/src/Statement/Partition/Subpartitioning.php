<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * `SUBPARTITION BY [LINEAR] HASH|KEY … [SUBPARTITIONS n]`: the subpartitioning of each partition.
 *
 * Mirrors PT_sub_partition_by_hash and PT_sub_partition_by_key: only HASH
 * and KEY can subpartition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-subpartitions.html.
 *
 * @visibility public
 * @example Holding a subpartitioning by key
 *     $sub = new \SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod(false, null, [new \SqlSemantics\Statement\Identifier\Name('id')]), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2'));
 *     $sub->count?->text // => '2'
 */
final class Subpartitioning implements Node
{
    use Snapshot;

    /**
     * @param PartitionMethod $method The HASH or KEY method
     * @param Numeral|null $count The SUBPARTITIONS count, when written
     */
    public function __construct(public readonly PartitionMethod $method, public readonly ?Numeral $count)
    {
        Check::input($method instanceof HashMethod || $method instanceof KeyMethod, 'Only HASH and KEY subpartition a partition.');
    }

    /**
     * Derives the method in the scope of the table.
     */
    public function deriveSubpartitioning(Derivation $derivation, Environment $scope): void
    {
        $this->method->deriveMethod($derivation, $scope);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('SUBPARTITION', 'BY')->node($this->method);
        if ($this->count !== null) {
            $out->keyword('SUBPARTITIONS')->node($this->count);
        }
    }
}
