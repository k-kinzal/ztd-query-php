<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange;
use SqlSemantics\Model\Definition\MySqlTable\Table;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\MySql\BinlogPolicy;
use SqlSemantics\Model\Maintenance\MySql\CheckOption;
use SqlSemantics\Model\Maintenance\MySql\RepairOption;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the standalone partition and tablespace commands of MySQL ALTER TABLE.
 * @visibility SqlSemantics
 */
final class PartitionChanges
{
    /**
     * Binds one standalone command from its leading keywords.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $command, Scope $scope, QueryContext $context): TableAlteration
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $command->tokens());
        $binlog = array_filter($command->find('opt_no_write_to_binlog'), Tree::hasTokens(...)) === [] ? BinlogPolicy::Write : BinlogPolicy::Omit;
        return match ($words[0] . ' ' . ($words[1] ?? '')) {
            'DISCARD TABLESPACE' => Table\TableCommand::DiscardTablespace,
            'IMPORT TABLESPACE' => Table\TableCommand::ImportTablespace,
            'DISCARD PARTITION', 'IMPORT PARTITION' => new PartitionChange\PartitionTablespaces(Table\TablespaceAction::from($words[0]), self::selection($command, $scope)),
            'ADD PARTITION' => self::add($command, $scope, $binlog),
            'DROP PARTITION' => PartitionDefinitions::build(static fn (): PartitionChange\DropPartitions => new PartitionChange\DropPartitions(Collections::nonEmpty(self::names($command, $scope))), $command),
            'REBUILD PARTITION', 'OPTIMIZE PARTITION', 'ANALYZE PARTITION' => new PartitionChange\ProcessPartitions(PartitionChange\PartitionProcess::from($words[0]), self::selection($command, $scope), $binlog),
            'CHECK PARTITION' => new PartitionChange\CheckPartitions(self::selection($command, $scope), array_map(static fn (Node $option): CheckOption => CheckOption::from(strtoupper(Tree::text($option))), Tree::outer($command, ['mi_check_type']))),
            'REPAIR PARTITION' => new PartitionChange\RepairPartitions(self::selection($command, $scope), $binlog, array_map(static fn (Node $option): RepairOption => RepairOption::from(strtoupper(Tree::text($option))), Tree::outer($command, ['mi_repair_type']))),
            'COALESCE PARTITION' => PartitionDefinitions::build(static fn (): PartitionChange\CoalescePartitions => new PartitionChange\CoalescePartitions(MySqlNumbers::read($command, InputViolation::PartitionDefinition, true), $binlog), $command),
            'TRUNCATE PARTITION' => new PartitionChange\TruncatePartitions(self::selection($command, $scope)),
            'REORGANIZE PARTITION' => self::reorganize($command, $scope, $binlog),
            'EXCHANGE PARTITION' => self::exchange($origin, $command, $scope, $context),
            default => self::secondary($command, $scope, $words[0]),
        };
    }

    /**
     * Binds ADD PARTITION with definitions or a count; a bare ADD PARTITION adds nothing and is rejected.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function add(Node $command, Scope $scope, BinlogPolicy $binlog): TableAlteration
    {
        $definitions = PartitionDefinitions::read($command, new Scope($scope->identifiers, queries: $scope->queries));
        if ($definitions !== []) {
            return PartitionDefinitions::build(static fn (): PartitionChange\AddPartitions => new PartitionChange\AddPartitions($definitions, $binlog), $command);
        }
        $count = array_values(array_filter(Tree::outer($command, ['real_ulong_num']), Tree::hasTokens(...)))[0] ?? throw new InvalidSql(InputViolation::PartitionDefinition, $command);
        return PartitionDefinitions::build(static fn (): PartitionChange\AddPartitionCount => new PartitionChange\AddPartitionCount(MySqlNumbers::read($count, InputViolation::PartitionDefinition, true), $binlog), $command);
    }

    /**
     * Binds REORGANIZE PARTITION with or without a replaced partition list.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function reorganize(Node $command, Scope $scope, BinlogPolicy $binlog): TableAlteration
    {
        $definitions = PartitionDefinitions::read($command, new Scope($scope->identifiers, queries: $scope->queries));
        if ($definitions === []) {
            return new PartitionChange\RebuildPartitioning($binlog);
        }
        return PartitionDefinitions::build(static fn (): PartitionChange\ReorganizePartitions => new PartitionChange\ReorganizePartitions(Collections::nonEmpty(self::names($command, $scope)), $definitions, $binlog), $command);
    }

    /**
     * Binds EXCHANGE PARTITION name WITH TABLE table; a trailing validation belongs to the statement.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function exchange(Origin $origin, Node $command, Scope $scope, QueryContext $context): PartitionChange\ExchangePartition
    {
        $table = TableOccurrence::resolve(Tree::child($command, ['table_ident']) ?? throw new UnclassifiedSql('EXCHANGE PARTITION requires a table.'), $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('EXCHANGE PARTITION requires a physical table.');
        }
        $name = self::names($command, $scope)[0] ?? '';
        return PartitionDefinitions::build(static fn (): PartitionChange\ExchangePartition => new PartitionChange\ExchangePartition($name, $table), $command, InputViolation::TableAlteration);
    }

    /**
     * Binds SECONDARY_LOAD or SECONDARY_UNLOAD with an optional partition list.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function secondary(Node $command, Scope $scope, string $word): PartitionChange\SecondaryLoad
    {
        $action = Table\SecondaryAction::tryFrom($word) ?? throw new UnclassifiedSql('Unclassified partition command: ' . Tree::text($command));
        $names = self::names($command, $scope);
        return PartitionDefinitions::build(static fn (): PartitionChange\SecondaryLoad => new PartitionChange\SecondaryLoad($action, $names), $command, InputViolation::TableAlteration);
    }

    /**
     * Reads ALL or a list of partition names.
     * @throws InvalidSql
     */
    public static function selection(Node $command, Scope $scope): AllPartitions|NamedPartitions
    {
        $list = Tree::child($command, ['all_or_alt_part_name_list']);
        if ($list !== null && strtoupper(Tree::text($list)) === 'ALL') {
            return AllPartitions::All;
        }
        $names = self::names($command, $scope);
        return PartitionDefinitions::build(static fn (): NamedPartitions => new NamedPartitions(Collections::nonEmpty($names)), $command, InputViolation::TableAlteration);
    }

    /**
     * Reads the partition names written in a command, excluding those inside partition definitions and the exchanged table.
     * @return list<string>
     */
    public static function names(Node $command, Scope $scope): array
    {
        $names = [];
        foreach (Tree::outer($command, ['ident', 'part_definition', 'table_ident']) as $node) {
            if ($node->name === 'ident' && Tree::hasTokens($node)) {
                $names[] = $scope->identifiers->name($node->tokens()[0]);
            }
        }
        return $names;
    }
}
