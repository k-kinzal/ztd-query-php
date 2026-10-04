<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Force;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\UpgradePartitioning;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;

/**
 * Lowers the actions of ALTER TABLE that are not column actions: keys, constraints, renames, conversion and options.
 *
 * Rule: MYSQL-ALTER-ITEM-001. Scope: alter_list_item (with
 * MYSQL-ALTER-COLUMN-001 for the column actions), opt_to. KEY and INDEX are
 * synonyms; TO, AS, `=` and no word after RENAME are the same request;
 * CHARACTER SET and CHARSET are synonyms. Keys, constraints and table
 * options are lowered by the table definition family. Constructs:
 * AddConstraint, DropElement, RenameElement, RenameTo, ToggleKeys,
 * IndexVisibility, ConstraintEnforcement, ConvertCharset, SetTableOptions,
 * Force, UpgradePartitioning, and the ALGORITHM and LOCK modifiers of 5.x
 * through MYSQL-ALTER-MODIFIER-001. Terminates: constant work per action.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class ItemRule
{
    /**
     * The DROP productions, by the kind of element, the position of its name and of RESTRICT or CASCADE.
     */
    private const DROPS = [
        'alter_list_item: DROP opt_column field_ident opt_restrict' => [ElementKind::Column, 2, 3],
        'alter_list_item: DROP opt_column ident opt_restrict' => [ElementKind::Column, 2, 3],
        'alter_list_item: DROP FOREIGN KEY_SYM field_ident' => [ElementKind::ForeignKey, 3, null],
        'alter_list_item: DROP FOREIGN KEY_SYM ident' => [ElementKind::ForeignKey, 3, null],
        'alter_list_item: DROP PRIMARY_SYM KEY_SYM' => [ElementKind::PrimaryKey, null, null],
        'alter_list_item: DROP key_or_index field_ident' => [ElementKind::Index, 2, null],
        'alter_list_item: DROP key_or_index ident' => [ElementKind::Index, 2, null],
        'alter_list_item: DROP CHECK_SYM ident' => [ElementKind::Check, 2, null],
        'alter_list_item: DROP CONSTRAINT ident' => [ElementKind::Constraint, 2, null],
    ];

    /**
     * The RENAME, ALTER INDEX, ALTER CHECK and ALTER CONSTRAINT productions, by the kind of element.
     */
    private const NAMED = [
        'alter_list_item: RENAME key_or_index field_ident TO_SYM field_ident' => ElementKind::Index,
        'alter_list_item: RENAME key_or_index ident TO_SYM ident' => ElementKind::Index,
        'alter_list_item: RENAME COLUMN_SYM ident TO_SYM ident' => ElementKind::Column,
        'alter_list_item: ALTER INDEX_SYM ident visibility' => ElementKind::Index,
        'alter_list_item: ALTER CHECK_SYM ident constraint_enforcement' => ElementKind::Check,
        'alter_list_item: ALTER CONSTRAINT ident constraint_enforcement' => ElementKind::Constraint,
    ];

    /**
     * The productions without operands that need no position table.
     */
    private const SIMPLE = [
        'alter_list_item: ADD key_def' => 'constraint', 'alter_list_item: ADD table_constraint_def' => 'constraint',
        'alter_list_item: DISABLE_SYM KEYS' => 'disable', 'alter_list_item: ENABLE_SYM KEYS' => 'enable',
        'alter_list_item: RENAME opt_to table_ident' => 'rename', 'alter_list_item: create_table_options_space_separated' => 'options',
        'alter_list_item: FORCE_SYM' => 'force', 'alter_list_item: UPGRADE_SYM PARTITIONING_SYM' => 'upgrade',
        'alter_list_item: alter_algorithm_option' => 'modifier', 'alter_list_item: alter_lock_option' => 'modifier',
        'alter_list_item: CONVERT_SYM TO_SYM charset charset_name_or_default opt_collate' => 'convert',
        'alter_list_item: CONVERT_SYM TO_SYM character_set charset_name opt_collate' => 'convert',
        'alter_list_item: CONVERT_SYM TO_SYM character_set DEFAULT_SYM opt_collate' => 'convert',
    ];

    /**
     * The productions of the optional word after RENAME.
     */
    private const TO = ['opt_to:' => true, 'opt_to: TO_SYM' => true, 'opt_to: EQ' => true, 'opt_to: AS' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one action: a node of `alter_list_item`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function item(Node $item): AlterCommand
    {
        $form = $this->lowering->form($item);
        $columns = new ColumnItemRule($this->lowering);
        if ($columns->claims($form->signature)) {
            return $columns->item($form);
        }
        if (isset(self::DROPS[$form->signature])) {
            return $this->drop($form);
        }
        if (isset(self::NAMED[$form->signature])) {
            return $this->named($form, self::NAMED[$form->signature]);
        }

        return $this->simple($form);
    }

    /**
     * Lowers an action without operands or with one operand another family lowers.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function simple(Form $form): AlterCommand
    {
        $definitions = $this->lowering->tableDefinitions;

        return match (self::SIMPLE[$form->signature] ?? throw ImplementationGap::production($form)) {
            'constraint' => new AddConstraint($definitions->tableElement($form->node(1))),
            'disable' => new ToggleKeys(false),
            'enable' => new ToggleKeys(true),
            'rename' => $this->rename($form),
            'options' => new SetTableOptions($definitions->tableOptions($form->node(0))),
            'force' => new Force(),
            'upgrade' => new UpgradePartitioning(),
            'modifier' => (new ModifierRule($this->lowering))->option($form->node(0)),
            'convert' => $this->convert($form),
        };
    }

    /**
     * Lowers a DROP of a column, key or constraint.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function drop(Form $form): DropElement
    {
        [$kind, $name, $behavior] = self::DROPS[$form->signature];
        $second = $form->node->children[1] ?? null;
        if ($second instanceof Node && $second->name === 'key_or_index') {
            $this->lowering->options->skip($second);
        } elseif ($second instanceof Node) {
            (new ColumnItemRule($this->lowering))->optional($second);
        }
        $columns = new ColumnItemRule($this->lowering);

        return new DropElement(
            $kind,
            $name === null ? null : $columns->name($form->node($name)),
            $behavior === null ? null : $this->lowering->options->dropBehavior($form->node($behavior)),
        );
    }

    /**
     * Lowers a RENAME of an index or column, or an ALTER of an index or constraint.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function named(Form $form, ElementKind $kind): AlterCommand
    {
        $columns = new ColumnItemRule($this->lowering);
        if ($form->token(0)->name === 'RENAME') {
            if ($kind === ElementKind::Index) {
                $this->lowering->options->skip($form->node(1));
            }

            return new RenameElement($kind, $columns->name($form->node(2)), $columns->name($form->node(4)));
        }
        $name = $this->lowering->names->identifier($form->node(2));
        if ($kind === ElementKind::Index) {
            return new IndexVisibility($name, $this->lowering->tableDefinitions->visible($form->node(3)));
        }

        return new ConstraintEnforcement($kind, $name, $this->lowering->tableDefinitions->enforced($form->node(3)));
    }

    /**
     * Lowers RENAME [TO | AS | =] of the table.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function rename(Form $form): RenameTo
    {
        $to = $this->lowering->form($form->node(1));
        if (!isset(self::TO[$to->signature])) {
            throw ImplementationGap::production($to);
        }

        return new RenameTo($this->lowering->names->qualified($form->node(2)));
    }

    /**
     * Lowers CONVERT TO CHARACTER SET.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function convert(Form $form): ConvertCharset
    {
        $this->lowering->options->skip($form->node(2));
        $charsets = $this->lowering->charsets;
        $charset = $form->node->children[3] instanceof Node ? $charsets->charset($form->node(3)) : $this->lowering->leaves->record(new CharsetName(null));

        return new ConvertCharset($charset, $charsets->collation($form->node(4)));
    }
}
