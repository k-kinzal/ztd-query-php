<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * `COALESCE PARTITION [NO_WRITE_TO_BINLOG] n`: a request to merge away n partitions of a HASH or KEY table.
 *
 * Mirrors PT_alter_table_coalesce_partition. LOCAL and NO_WRITE_TO_BINLOG
 * are synonyms.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-management-hash-key.html.
 *
 * @visibility public
 * @example Removing two hash partitions
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t COALESCE PARTITION 2')->commands[0]->count->text // => '2'
 */
final class CoalescePartition implements StandaloneCommand
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ?\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption $binlog The binary logging suppression keyword, or null when absent
     * @param Numeral $count The number of partitions to remove
     */
    public function __construct(public readonly ?\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption $binlog, public readonly Numeral $count, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Checks that the partition count is an integer token before table resolution.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integer($derivation, $this->count);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('COALESCE', 'PARTITION');
        if ($this->binlog !== null) {
            $out->keyword($this->binlog->value);
        }
        $out->node($this->count);
    }
}
