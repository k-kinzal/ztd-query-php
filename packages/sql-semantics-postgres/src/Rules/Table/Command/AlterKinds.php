<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AddConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AddColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AddIdentity;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AlterIdentity;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnAttributes;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnStatistics;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnStorageChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\DefaultChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\DropColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\SetExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\TypeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\DropConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\InheritanceChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ColumnForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OfComposite;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\RelationOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentity;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\SetAccessMethod;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnStorage;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\AlterAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the actions of ALTER TABLE against the kind of the altered relation.
 *
 * Rule: PG-ALTER-KIND-001. Each action accepts only some kinds of relation
 * (ATPrepCmd / ATSimplePermissions): for example ADD COLUMN, DROP COLUMN,
 * SET DATA TYPE, ADD CONSTRAINT and the trigger actions a base table or a
 * foreign table; ALTER COLUMN ... SET DEFAULT and the identity actions also
 * a view; SET STATISTICS, column SET/RESET and SET STORAGE also a
 * materialized view; SET ( storage parameters ) a base table, a view or a
 * materialized view; SET LOGGED and SET UNLOGGED a base table or a sequence;
 * the OPTIONS actions only a foreign table; ADD CONSTRAINT ... USING INDEX,
 * ALTER CONSTRAINT, OF, NOT OF, the row security and rule actions and the
 * partition actions only a base table; OWNER TO every kind. An action on a
 * relation of another declared kind is reported as `ALTER action %s cannot
 * be performed on relation "%s"` with the server's name for the action.
 * The parent of INHERIT and NO INHERIT must itself be a base table or a
 * foreign table, and a foreign table takes no primary key, unique,
 * exclusion or foreign key constraint. Source: https://www.postgresql.org/docs/17/sql-altertable.html,
 * `tablecmds.c` (ATPrepCmd, alter_table_type_to_string, ATExecAddInherit).
 * Termination: one check per action. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AlterKinds
{
    private const TABLE = [RelationKind::BaseTable];

    private const TABLE_OR_FOREIGN = [RelationKind::BaseTable, RelationKind::ForeignTable];

    private const TABLE_VIEW_OR_FOREIGN = [RelationKind::BaseTable, RelationKind::View, RelationKind::ForeignTable];

    private const TABLE_OR_MATERIALIZED = [RelationKind::BaseTable, RelationKind::MaterializedView];

    private const TABLE_MATERIALIZED_OR_FOREIGN = [RelationKind::BaseTable, RelationKind::MaterializedView, RelationKind::ForeignTable];

    /**
     * Reports the actions the declared kind of the relation does not accept.
     *
     * @param list<AlterCommand> $commands
     */
    public function check(Derivation $derivation, ?RelationKind $kind, Name $relation, array $commands): void
    {
        foreach ($kind === null ? [] : $commands as $command) {
            $accepted = $this->accepted($command);
            if ($accepted !== null && !in_array($kind, $accepted[1], true)) {
                $derivation->report(new KindProblem(KindRule::AlterAction, $relation, $accepted[0]));
            }
        }
        foreach ($commands as $command) {
            if ($command instanceof InheritanceChange && !$command->remove) {
                $this->parent($derivation, $command);
            }
        }
    }

    /**
     * Reports the constraints the added columns and constraints would give a foreign table (PG-RELATION-KIND-001).
     *
     * @param list<AlterCommand> $commands
     */
    public function foreign(Derivation $derivation, array $commands): void
    {
        $elements = [];
        foreach ($commands as $command) {
            if ($command instanceof AddColumn) {
                $elements[] = $command->column;
            } elseif ($command instanceof AddConstraint) {
                $elements[] = $command->constraint;
            }
        }
        (new RelationKinds())->foreign($derivation, $elements);
    }

    /**
     * Reports a new parent of INHERIT that is neither a base table nor a foreign table.
     */
    public function parent(Derivation $derivation, InheritanceChange $command): void
    {
        $kind = (new RelationKinds())->declared($derivation, $command->parent->name);
        if ($kind !== null && !in_array($kind, self::TABLE_OR_FOREIGN, true)) {
            $derivation->report(new KindProblem(KindRule::AlterAction, $command->parent->name->name, AlterAction::Inherit));
        }
    }

    /**
     * Answers the server's name of an action and the kinds of relation it accepts; null for an action every kind accepts.
     *
     * @return array{AlterAction, list<RelationKind>}|null
     */
    public function accepted(AlterCommand $command): ?array
    {
        return match (true) {
            $command instanceof ColumnAction => $this->column($command->kind),
            $command instanceof TableAction => $this->table($command->kind),
            $command instanceof NamedAction => $this->named($command->kind),
            default => $this->other($command),
        };
    }

    /**
     * Answers the name and the accepted kinds of the actions that are neither column, table nor named actions.
     *
     * @return array{AlterAction, list<RelationKind>}|null
     */
    public function other(AlterCommand $command): ?array
    {
        return match (true) {
            $command instanceof AddColumn => [AlterAction::AddColumn, self::TABLE_OR_FOREIGN],
            $command instanceof DropColumn => [AlterAction::DropColumn, self::TABLE_OR_FOREIGN],
            $command instanceof TypeChange => [AlterAction::SetDataType, self::TABLE_OR_FOREIGN],
            $command instanceof DefaultChange => [AlterAction::SetDefault, self::TABLE_VIEW_OR_FOREIGN],
            $command instanceof AddIdentity => [AlterAction::AddIdentity, self::TABLE_VIEW_OR_FOREIGN],
            $command instanceof AlterIdentity => [AlterAction::SetColumnAttributes, self::TABLE_VIEW_OR_FOREIGN],
            $command instanceof SetExpression => [AlterAction::SetExpression, self::TABLE_OR_FOREIGN],
            $command instanceof ColumnStatistics => [AlterAction::SetStatistics, self::TABLE_MATERIALIZED_OR_FOREIGN],
            $command instanceof ColumnAttributes => [$command->reset ? AlterAction::ResetOptions : AlterAction::SetColumnAttributes, self::TABLE_MATERIALIZED_OR_FOREIGN],
            $command instanceof ColumnStorageChange => $command->setting instanceof ColumnStorage ? [AlterAction::SetStorage, self::TABLE_MATERIALIZED_OR_FOREIGN] : [AlterAction::SetCompression, self::TABLE_OR_MATERIALIZED],
            $command instanceof ColumnForeignOptions => [AlterAction::ColumnOptions, [RelationKind::ForeignTable]],
            default => $this->relationWide($command),
        };
    }

    /**
     * Answers the name and the accepted kinds of the actions on the relation as a whole.
     *
     * @return array{AlterAction, list<RelationKind>}|null
     */
    public function relationWide(AlterCommand $command): ?array
    {
        return match (true) {
            $command instanceof AddConstraint => [AlterAction::AddConstraint, $command->constraint instanceof IndexConstraint ? self::TABLE : self::TABLE_OR_FOREIGN],
            $command instanceof DropConstraint => [AlterAction::DropConstraint, self::TABLE_OR_FOREIGN],
            $command instanceof AlterConstraint => [AlterAction::AlterConstraint, self::TABLE],
            $command instanceof InheritanceChange => [$command->remove ? AlterAction::NoInherit : AlterAction::Inherit, self::TABLE_OR_FOREIGN],
            $command instanceof OfComposite => [AlterAction::Of, self::TABLE],
            $command instanceof ReplicaIdentity => [AlterAction::ReplicaIdentity, self::TABLE_OR_MATERIALIZED],
            $command instanceof RelationOptions => [$command->reset ? AlterAction::ResetRelationOptions : AlterAction::SetRelationOptions, [RelationKind::BaseTable, RelationKind::View, RelationKind::MaterializedView]],
            $command instanceof SetAccessMethod => [AlterAction::SetAccessMethod, self::TABLE_OR_MATERIALIZED],
            $command instanceof ForeignOptions => [AlterAction::Options, [RelationKind::ForeignTable]],
            $command instanceof AttachPartition => [AlterAction::AttachPartition, self::TABLE],
            $command instanceof AttachIndex => [AlterAction::AttachPartition, []],
            $command instanceof DetachPartition => [$command->mode === DetachMode::Finalize ? AlterAction::DetachPartitionFinalize : AlterAction::DetachPartition, self::TABLE],
            default => null,
        };
    }

    /**
     * Answers the name and the accepted kinds of a column action without operand.
     *
     * @return array{AlterAction, list<RelationKind>}
     */
    public function column(ColumnActionKind $kind): array
    {
        return match ($kind) {
            ColumnActionKind::SetNotNull => [AlterAction::SetNotNull, self::TABLE_OR_FOREIGN],
            ColumnActionKind::DropNotNull => [AlterAction::DropNotNull, self::TABLE_OR_FOREIGN],
            ColumnActionKind::DropDefault => [AlterAction::SetDefault, self::TABLE_VIEW_OR_FOREIGN],
            ColumnActionKind::DropExpression, ColumnActionKind::DropExpressionIfExists => [AlterAction::DropExpression, self::TABLE_OR_FOREIGN],
            ColumnActionKind::DropIdentity, ColumnActionKind::DropIdentityIfExists => [AlterAction::DropIdentity, self::TABLE_VIEW_OR_FOREIGN],
        };
    }

    /**
     * Answers the name and the accepted kinds of an action on the relation without operand.
     *
     * @return array{AlterAction, list<RelationKind>}
     */
    public function table(TableActionKind $kind): array
    {
        return match ($kind) {
            TableActionKind::SetWithoutOids => [AlterAction::SetWithoutOids, self::TABLE_OR_FOREIGN],
            TableActionKind::SetWithoutCluster => [AlterAction::SetWithoutCluster, self::TABLE_OR_MATERIALIZED],
            TableActionKind::SetLogged => [AlterAction::SetLogged, [RelationKind::BaseTable, RelationKind::Sequence]],
            TableActionKind::SetUnlogged => [AlterAction::SetUnlogged, [RelationKind::BaseTable, RelationKind::Sequence]],
            TableActionKind::NotOf => [AlterAction::NotOf, self::TABLE],
            TableActionKind::EnableRowSecurity => [AlterAction::EnableRowSecurity, self::TABLE],
            TableActionKind::DisableRowSecurity => [AlterAction::DisableRowSecurity, self::TABLE],
            TableActionKind::ForceRowSecurity => [AlterAction::ForceRowSecurity, self::TABLE],
            TableActionKind::NoForceRowSecurity => [AlterAction::NoForceRowSecurity, self::TABLE],
            TableActionKind::EnableAllTriggers => [AlterAction::EnableAllTriggers, self::TABLE_OR_FOREIGN],
            TableActionKind::EnableUserTriggers => [AlterAction::EnableUserTriggers, self::TABLE_OR_FOREIGN],
            TableActionKind::DisableAllTriggers => [AlterAction::DisableAllTriggers, self::TABLE_OR_FOREIGN],
            TableActionKind::DisableUserTriggers => [AlterAction::DisableUserTriggers, self::TABLE_OR_FOREIGN],
        };
    }

    /**
     * Answers the name and the accepted kinds of an action on a named object of the relation.
     *
     * @return array{AlterAction, list<RelationKind>}
     */
    public function named(NamedActionKind $kind): array
    {
        return match ($kind) {
            NamedActionKind::ClusterOn => [AlterAction::ClusterOn, self::TABLE_OR_MATERIALIZED],
            NamedActionKind::ValidateConstraint => [AlterAction::ValidateConstraint, self::TABLE_OR_FOREIGN],
            NamedActionKind::SetTablespace => [AlterAction::SetTablespace, self::TABLE_OR_MATERIALIZED],
            NamedActionKind::EnableTrigger => [AlterAction::EnableTrigger, self::TABLE_OR_FOREIGN],
            NamedActionKind::EnableAlwaysTrigger => [AlterAction::EnableAlwaysTrigger, self::TABLE_OR_FOREIGN],
            NamedActionKind::EnableReplicaTrigger => [AlterAction::EnableReplicaTrigger, self::TABLE_OR_FOREIGN],
            NamedActionKind::DisableTrigger => [AlterAction::DisableTrigger, self::TABLE_OR_FOREIGN],
            NamedActionKind::EnableRule => [AlterAction::EnableRule, self::TABLE],
            NamedActionKind::EnableAlwaysRule => [AlterAction::EnableAlwaysRule, self::TABLE],
            NamedActionKind::EnableReplicaRule => [AlterAction::EnableReplicaRule, self::TABLE],
            NamedActionKind::DisableRule => [AlterAction::DisableRule, self::TABLE],
        };
    }
}
