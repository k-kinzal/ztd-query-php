<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\Column;
use SqlSemantics\Model\Definition\MySqlTable\Key;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange;
use SqlSemantics\Model\Definition\MySqlTable\Table;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates how MySQL ALTER TABLE alterations combine and in which grammar releases they exist.
 * @visibility SqlSemantics
 */
final class AlterationRules
{
    /**
     * A standalone alteration is the only one, and a partitioning change comes last and at most once.
     * @param list<TableAlteration> $alterations
     * @throws InvalidStructure
     */
    public static function combination(Origin $origin, array $alterations, TableAlgorithm $algorithm, IndexLock $lock): void
    {
        $standalone = array_filter($alterations, self::standalone(...));
        if ($standalone !== [] && count($alterations) !== 1) {
            throw new InvalidStructure('A partition or tablespace command is the only alteration of its statement.');
        }
        if ($standalone !== [] && ($algorithm !== TableAlgorithm::Default || $lock !== IndexLock::Default)) {
            TableInvariant::since57($origin, 'ALGORITHM or LOCK with a partition or tablespace command');
        }
        $partitioning = array_keys(array_filter($alterations, static fn (TableAlteration $alteration): bool => $alteration instanceof Table\RepartitionTable || $alteration === Table\TableCommand::RemovePartitioning));
        if (count($partitioning) > 1 || ($partitioning !== [] && $partitioning[0] !== count($alterations) - 1)) {
            throw new InvalidStructure('A partitioning change is the last alteration of its statement.');
        }
    }

    /**
     * Reports whether an alteration must stand alone.
     */
    public static function standalone(TableAlteration $alteration): bool
    {
        return $alteration instanceof PartitionChange\AddPartitions
            || $alteration instanceof PartitionChange\AddPartitionCount
            || $alteration instanceof PartitionChange\DropPartitions
            || $alteration instanceof PartitionChange\ProcessPartitions
            || $alteration instanceof PartitionChange\CheckPartitions
            || $alteration instanceof PartitionChange\RepairPartitions
            || $alteration instanceof PartitionChange\TruncatePartitions
            || $alteration instanceof PartitionChange\CoalescePartitions
            || $alteration instanceof PartitionChange\ReorganizePartitions
            || $alteration instanceof PartitionChange\RebuildPartitioning
            || $alteration instanceof PartitionChange\ExchangePartition
            || $alteration instanceof PartitionChange\PartitionTablespaces
            || $alteration instanceof PartitionChange\SecondaryLoad
            || $alteration === Table\TableCommand::DiscardTablespace
            || $alteration === Table\TableCommand::ImportTablespace;
    }

    /**
     * Rejects alterations the statement's grammar release does not define.
     * @throws InvalidStructure
     */
    public static function release(Origin $origin, TableAlteration $alteration): void
    {
        if ($alteration instanceof Column\RenameColumn || $alteration instanceof Column\SetColumnVisibility || $alteration instanceof Key\SetIndexVisibility || $alteration instanceof Key\SetConstraintEnforcement || $alteration instanceof PartitionChange\SecondaryLoad) {
            TableInvariant::modern($origin, 'This alteration');
        }
        if ($alteration instanceof Key\DropKey && in_array($alteration->kind, [Key\KeyKind::Check, Key\KeyKind::Constraint], true)) {
            TableInvariant::modern($origin, 'DROP CHECK and DROP CONSTRAINT');
        }
        if ($alteration instanceof Key\RenameIndex || $alteration instanceof PartitionChange\PartitionTablespaces) {
            TableInvariant::since57($origin, 'This alteration');
        }
        if ($alteration === Table\TableCommand::UpgradePartitioning) {
            TableInvariant::only($origin, ['mysql-5.7.44'], 'UPGRADE PARTITIONING');
        }
    }
}
