<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Platform\MySql\Statement\Server\CheckOption;
use SqlSemantics\Platform\MySql\Statement\Server\RepairOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `REBUILD | OPTIMIZE | ANALYZE | CHECK | REPAIR | TRUNCATE PARTITION ALL | p, …`: a maintenance operation on partitions.
 *
 * Mirrors PT_alter_table_rebuild_partition, …_optimize_partition,
 * …_analyze_partition, …_check_partition, …_repair_partition and
 * …_truncate_partition. LOCAL and NO_WRITE_TO_BINLOG are synonyms. The
 * grammar of 5.6 and 5.7 reads NO_WRITE_TO_BINLOG a second time after the
 * partitions of OPTIMIZE; the server ignores that second one, but it is
 * part of the statement as written and is kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-maintenance.html,
 * https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_yacc.yy (OPTIMIZE PARTITION_SYM).
 *
 * @visibility public
 * @example Rebuilding every partition
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t REBUILD PARTITION ALL');
 *     [$alter->statement->commands[0]->kind, $alter->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind::Rebuild, 'ALTER TABLE t REBUILD PARTITION ALL']
 */
final class MaintainPartitions implements StandaloneCommand
{
    use Snapshot;

    /**
     * @var list<CheckOption>|list<RepairOption> The options of CHECK or REPAIR in order
     */
    public readonly array $options;

    /**
     * @param MaintenanceKind $kind The operation
     * @param bool $local Whether NO_WRITE_TO_BINLOG or LOCAL is written before the partitions
     * @param PartitionSelection $partitions The partitions
     * @param list<CheckOption>|list<RepairOption> $options The options of CHECK or REPAIR in order
     * @param bool $ignoredLocal Whether NO_WRITE_TO_BINLOG or LOCAL is written after the partitions of OPTIMIZE (5.6, 5.7)
     */
    public function __construct(
        public readonly MaintenanceKind $kind,
        public readonly bool $local,
        public readonly PartitionSelection $partitions,
        array $options = [],
        public readonly bool $ignoredLocal = false,
    ) {
        Check::input($kind->logged() || !$local, 'CHECK and TRUNCATE PARTITION take no NO_WRITE_TO_BINLOG.');
        Check::input(!$ignoredLocal || $kind === MaintenanceKind::Optimize, 'Only OPTIMIZE PARTITION reads NO_WRITE_TO_BINLOG after the partitions.');
        Check::input($options === [] || $kind === MaintenanceKind::Check || $kind === MaintenanceKind::Repair, 'Only CHECK and REPAIR PARTITION take options.');
        $this->options = $kind === MaintenanceKind::Repair
            ? Check::listOf($options, RepairOption::class, 'REPAIR PARTITION takes repair options.')
            : Check::listOf($options, CheckOption::class, 'CHECK PARTITION takes check options.');
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value, 'PARTITION');
        if ($this->local) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
        $out->node($this->partitions);
        foreach ($this->options as $option) {
            $out->keyword(...explode(' ', $option->value));
        }
        if ($this->ignoredLocal) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
    }
}
