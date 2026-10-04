<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
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
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\IdentityGeneration;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\IdentityRestart;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\IdentitySetting;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\SetExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\TypeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\DropConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\InheritanceChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ColumnForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OfComposite;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OwnerTo;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\RelationOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentity;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentityKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\SetAccessMethod;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the actions of ALTER TABLE.
 *
 * Rule: PG-ALTER-COMMAND-LOWER-001. Scope: `alter_table_cmd`,
 * `alter_column_default`, `alter_using`, `replica_identity`,
 * `alter_identity_column_option_list`, `alter_identity_column_option`,
 * `set_statistics_value`, `set_access_method_name`. The optional COLUMN
 * words are noise (LeafNoise `opt_column`, TableNoise `ADD COLUMN`), and so
 * is SET DATA before TYPE (LeafNoise). Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterCommandRule
{
    /**
     * The relation actions without an operand.
     */
    private const TABLE = [
        'alter_table_cmd: SET WITHOUT OIDS' => TableActionKind::SetWithoutOids, 'alter_table_cmd: SET WITHOUT CLUSTER' => TableActionKind::SetWithoutCluster,
        'alter_table_cmd: SET LOGGED' => TableActionKind::SetLogged, 'alter_table_cmd: SET UNLOGGED' => TableActionKind::SetUnlogged,
        'alter_table_cmd: NOT OF' => TableActionKind::NotOf,
        'alter_table_cmd: ENABLE_P ROW LEVEL SECURITY' => TableActionKind::EnableRowSecurity, 'alter_table_cmd: DISABLE_P ROW LEVEL SECURITY' => TableActionKind::DisableRowSecurity,
        'alter_table_cmd: FORCE ROW LEVEL SECURITY' => TableActionKind::ForceRowSecurity, 'alter_table_cmd: NO FORCE ROW LEVEL SECURITY' => TableActionKind::NoForceRowSecurity,
        'alter_table_cmd: ENABLE_P TRIGGER ALL' => TableActionKind::EnableAllTriggers, 'alter_table_cmd: ENABLE_P TRIGGER USER' => TableActionKind::EnableUserTriggers,
        'alter_table_cmd: DISABLE_P TRIGGER ALL' => TableActionKind::DisableAllTriggers, 'alter_table_cmd: DISABLE_P TRIGGER USER' => TableActionKind::DisableUserTriggers,
    ];

    /**
     * The relation actions on a named object, with the position of the name.
     */
    private const NAMED = [
        'alter_table_cmd: CLUSTER ON name' => [NamedActionKind::ClusterOn, 2], 'alter_table_cmd: VALIDATE CONSTRAINT name' => [NamedActionKind::ValidateConstraint, 2],
        'alter_table_cmd: SET TABLESPACE name' => [NamedActionKind::SetTablespace, 2],
        'alter_table_cmd: ENABLE_P TRIGGER name' => [NamedActionKind::EnableTrigger, 2], 'alter_table_cmd: ENABLE_P ALWAYS TRIGGER name' => [NamedActionKind::EnableAlwaysTrigger, 3],
        'alter_table_cmd: ENABLE_P REPLICA TRIGGER name' => [NamedActionKind::EnableReplicaTrigger, 3], 'alter_table_cmd: DISABLE_P TRIGGER name' => [NamedActionKind::DisableTrigger, 2],
        'alter_table_cmd: ENABLE_P RULE name' => [NamedActionKind::EnableRule, 2], 'alter_table_cmd: ENABLE_P ALWAYS RULE name' => [NamedActionKind::EnableAlwaysRule, 3],
        'alter_table_cmd: ENABLE_P REPLICA RULE name' => [NamedActionKind::EnableReplicaRule, 3], 'alter_table_cmd: DISABLE_P RULE name' => [NamedActionKind::DisableRule, 2],
    ];

    /**
     * The column actions without an operand; the column is at position 2.
     */
    private const COLUMN = [
        'alter_table_cmd: ALTER opt_column ColId DROP NOT NULL_P' => ColumnActionKind::DropNotNull,
        'alter_table_cmd: ALTER opt_column ColId SET NOT NULL_P' => ColumnActionKind::SetNotNull,
        'alter_table_cmd: ALTER opt_column ColId DROP EXPRESSION' => ColumnActionKind::DropExpression,
        'alter_table_cmd: ALTER opt_column ColId DROP EXPRESSION IF_P EXISTS' => ColumnActionKind::DropExpressionIfExists,
        'alter_table_cmd: ALTER opt_column ColId DROP IDENTITY_P' => ColumnActionKind::DropIdentity,
        'alter_table_cmd: ALTER opt_column ColId DROP IDENTITY_P IF_P EXISTS' => ColumnActionKind::DropIdentityIfExists,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `alter_table_cmd`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function command(Node $command): AlterCommand
    {
        $form = $this->lowering->productions->form($command);
        if (isset(self::TABLE[$form->signature])) {
            return new TableAction(self::TABLE[$form->signature]);
        }
        if (isset(self::NAMED[$form->signature])) {
            [$kind, $at] = self::NAMED[$form->signature];

            return new NamedAction($kind, $this->lowering->names->name($form->node($at)));
        }
        if (isset(self::COLUMN[$form->signature])) {
            return new ColumnAction(self::COLUMN[$form->signature], $this->lowering->names->name($form->node(2)));
        }

        $second = $form->node->children[1] ?? null;

        return $second instanceof Node && $second->name === 'opt_column' && $form->token(0)->name === 'ALTER' ? $this->column($form) : $this->relation($form);
    }

    /**
     * Lowers an `alter_table_cmd` that changes one column.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function column(Form $form): AlterCommand
    {
        $column = $form->signature === 'alter_table_cmd: ALTER opt_column Iconst SET STATISTICS SignedIconst' || $form->signature === 'alter_table_cmd: ALTER opt_column Iconst SET STATISTICS set_statistics_value'
            ? $this->lowering->literals->integer($form->node(2))
            : $this->lowering->names->name($form->node(2));
        if (!$column instanceof \SqlSemantics\Statement\Identifier\Name) {
            return new ColumnStatistics($column, $this->statistics($form->node(5)));
        }
        $columns = new ColumnRule($this->lowering);

        return match ($form->signature) {
            'alter_table_cmd: ALTER opt_column ColId alter_column_default' => $this->defaulted($column, $form->node(3)),
            'alter_table_cmd: ALTER opt_column ColId SET STATISTICS SignedIconst', 'alter_table_cmd: ALTER opt_column ColId SET STATISTICS set_statistics_value' => new ColumnStatistics($column, $this->statistics($form->node(5))),
            'alter_table_cmd: ALTER opt_column ColId SET reloptions' => new ColumnAttributes($column, false, $this->lowering->options->definitions($form->node(4))),
            'alter_table_cmd: ALTER opt_column ColId RESET reloptions' => new ColumnAttributes($column, true, $this->lowering->options->definitions($form->node(4))),
            'alter_table_cmd: ALTER opt_column ColId SET column_storage' => new ColumnStorageChange($column, $columns->storage($form->node(4)) ?? throw ImplementationGap::production($form)),
            'alter_table_cmd: ALTER opt_column ColId SET column_compression' => new ColumnStorageChange($column, $columns->compression($form->node(4)) ?? throw ImplementationGap::production($form)),
            'alter_table_cmd: ALTER opt_column ColId ADD_P GENERATED generated_when AS IDENTITY_P OptParenthesizedSeqOptList' => new AddIdentity($column, $columns->when($form->node(5)), (new SequenceRule($this->lowering))->parenthesized($form->node(8))),
            'alter_table_cmd: ALTER opt_column ColId alter_identity_column_option_list' => new AlterIdentity($column, $this->identity($form->node(3))),
            'alter_table_cmd: ALTER opt_column ColId opt_set_data TYPE_P Typename opt_collate_clause alter_using' => new TypeChange(
                $column,
                $this->lowering->types->typeName($form->node(5)),
                $this->lowering->names->optionalDotted($form->node(6)),
                $this->using($form->node(7)),
            ),
            'alter_table_cmd: ALTER opt_column ColId alter_generic_options' => new ColumnForeignOptions($column, $this->lowering->options->alteredOptions($form->node(3))),
            'alter_table_cmd: ALTER opt_column ColId SET EXPRESSION AS ( a_expr )' => new SetExpression($column, $this->lowering->expressions->expression($form->node(7))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `alter_table_cmd` that adds or drops a column or changes the relation.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function relation(Form $form): AlterCommand
    {
        $names = $this->lowering->names;
        $columns = new ColumnRule($this->lowering);
        $flags = $this->lowering->flags;

        return match ($form->signature) {
            'alter_table_cmd: ADD_P columnDef' => new AddColumn($columns->column($form->node(1))),
            'alter_table_cmd: ADD_P IF_P NOT EXISTS columnDef' => new AddColumn($columns->column($form->node(4)), true),
            'alter_table_cmd: ADD_P COLUMN columnDef' => new AddColumn($columns->column($form->node(2))),
            'alter_table_cmd: ADD_P COLUMN IF_P NOT EXISTS columnDef' => new AddColumn($columns->column($form->node(5)), true),
            'alter_table_cmd: DROP opt_column IF_P EXISTS ColId opt_drop_behavior' => new DropColumn($names->name($form->node(4)), true, $flags->dropBehavior($form->node(5))),
            'alter_table_cmd: DROP opt_column ColId opt_drop_behavior' => new DropColumn($names->name($form->node(2)), false, $flags->dropBehavior($form->node(3))),
            'alter_table_cmd: ADD_P TableConstraint' => new AddConstraint($this->lowering->tables->tableConstraint($form->node(1))),
            'alter_table_cmd: ALTER CONSTRAINT name ConstraintAttributeSpec' => new AlterConstraint($names->name($form->node(2)), $this->lowering->tables->constraintAttributes($form->node(3))),
            'alter_table_cmd: DROP CONSTRAINT IF_P EXISTS name opt_drop_behavior' => new DropConstraint($names->name($form->node(4)), true, $flags->dropBehavior($form->node(5))),
            'alter_table_cmd: DROP CONSTRAINT name opt_drop_behavior' => new DropConstraint($names->name($form->node(2)), false, $flags->dropBehavior($form->node(3))),
            default => $this->setting($form),
        };
    }

    /**
     * Lowers an `alter_table_cmd` that changes a property of the relation.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function setting(Form $form): AlterCommand
    {
        $names = $this->lowering->names;

        return match ($form->signature) {
            'alter_table_cmd: INHERIT qualified_name' => new InheritanceChange(new ParentTable($names->qualified($form->node(1)))),
            'alter_table_cmd: NO INHERIT qualified_name' => new InheritanceChange(new ParentTable($names->qualified($form->node(2))), true),
            'alter_table_cmd: OF any_name' => new OfComposite($names->dotted($form->node(1))),
            'alter_table_cmd: OWNER TO RoleSpec' => new OwnerTo($this->lowering->roles->role($form->node(2))),
            'alter_table_cmd: SET ACCESS METHOD name' => new SetAccessMethod($names->name($form->node(3))),
            'alter_table_cmd: SET ACCESS METHOD set_access_method_name' => new SetAccessMethod($this->method($form->node(3))),
            'alter_table_cmd: SET reloptions' => new RelationOptions(false, $this->lowering->options->definitions($form->node(1))),
            'alter_table_cmd: RESET reloptions' => new RelationOptions(true, $this->lowering->options->definitions($form->node(1))),
            'alter_table_cmd: REPLICA IDENTITY_P replica_identity' => $this->replica($form->node(2)),
            'alter_table_cmd: alter_generic_options' => new ForeignOptions($this->lowering->options->alteredOptions($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `alter_column_default` for a column.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function defaulted(\SqlSemantics\Statement\Identifier\Name $column, Node $change): AlterCommand
    {
        $value = $this->defaultValue($change);

        return $value === null ? new ColumnAction(ColumnActionKind::DropDefault, $column) : new DefaultChange($column, $value);
    }

    /**
     * Lowers `alter_column_default`: the expression of SET DEFAULT, or null for DROP DEFAULT.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function defaultValue(Node $change): ?Scalar
    {
        $form = $this->lowering->productions->form($change);

        return match ($form->signature) {
            'alter_column_default: SET DEFAULT a_expr' => $this->lowering->expressions->expression($form->node(2)),
            'alter_column_default: DROP DEFAULT' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `alter_using`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function using(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'alter_using: USING a_expr' => $this->lowering->expressions->expression($form->node(1)),
            'alter_using:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the statistics target: `SignedIconst` or `set_statistics_value`; DEFAULT is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statistics(Node $value): ?SignedNumber
    {
        if ($value->name === 'SignedIconst') {
            return $this->lowering->literals->signed($value);
        }
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'set_statistics_value: SignedIconst' => $this->lowering->literals->signed($form->node(0)),
            'set_statistics_value: DEFAULT' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `set_access_method_name`; DEFAULT is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function method(Node $value): ?\SqlSemantics\Statement\Identifier\Name
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'set_access_method_name: ColId' => $this->lowering->names->name($form->node(0)),
            'set_access_method_name: DEFAULT' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `replica_identity`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function replica(Node $identity): ReplicaIdentity
    {
        $form = $this->lowering->productions->form($identity);

        return match ($form->signature) {
            'replica_identity: NOTHING' => new ReplicaIdentity(ReplicaIdentityKind::Nothing),
            'replica_identity: FULL' => new ReplicaIdentity(ReplicaIdentityKind::Full),
            'replica_identity: DEFAULT' => new ReplicaIdentity(ReplicaIdentityKind::Default),
            'replica_identity: USING INDEX name' => new ReplicaIdentity(ReplicaIdentityKind::Index, $this->lowering->names->name($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `alter_identity_column_option_list`.
     *
     * @return list<IdentityRestart|IdentitySetting|IdentityGeneration>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function identity(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'alter_identity_column_option_list: alter_identity_column_option', 'alter_identity_column_option_list: alter_identity_column_option_list alter_identity_column_option') as $item) {
            $form = $this->lowering->productions->form($item);
            $options[] = match ($form->signature) {
                'alter_identity_column_option: RESTART' => new IdentityRestart(),
                'alter_identity_column_option: RESTART opt_with NumericOnly' => new IdentityRestart($this->lowering->literals->signed($form->node(2))),
                'alter_identity_column_option: SET SeqOptElem' => new IdentitySetting((new SequenceRule($this->lowering))->option($form->node(1))),
                'alter_identity_column_option: SET GENERATED generated_when' => new IdentityGeneration((new ColumnRule($this->lowering))->when($form->node(2))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $options;
    }
}
