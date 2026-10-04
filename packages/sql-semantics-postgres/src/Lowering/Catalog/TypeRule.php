<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AddAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AddEnumLabel;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AlterComposite;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AlterTypeOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AttributeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\DropAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\DropEnumLabel;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\EnumPosition;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\RenameEnumLabel;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\RetypeAttribute;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the ALTER TYPE commands of enum, composite and base types.
 *
 * Rule: PG-TYPE-LOWER-001. Scope: `AlterEnumStmt`, `AlterCompositeTypeStmt`,
 * `alter_type_cmds`, `alter_type_cmd`, `AlterTypeStmt`. Constructors:
 * `AddEnumLabel`, `EnumPosition`, `RenameEnumLabel`, `DropEnumLabel`,
 * `AlterComposite`, `AddAttribute`, `DropAttribute`, `RetypeAttribute`,
 * `AlterTypeOptions`. The optional SET DATA is noise (LeafNoise).
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class TypeRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an ALTER TYPE command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $name = $this->lowering->names->dotted($form->node(2));
        $strings = $this->lowering->literals;
        $flags = new CatalogFlags($this->lowering);

        return match ($form->signature) {
            'AlterEnumStmt: ALTER TYPE_P any_name ADD_P VALUE_P opt_if_not_exists Sconst' => new AddEnumLabel($name, $flags->present($form->node(5)), $strings->string($form->node(6))),
            'AlterEnumStmt: ALTER TYPE_P any_name ADD_P VALUE_P opt_if_not_exists Sconst BEFORE Sconst' => new AddEnumLabel($name, $flags->present($form->node(5)), $strings->string($form->node(6)), new EnumPosition(false, $strings->string($form->node(8)))),
            'AlterEnumStmt: ALTER TYPE_P any_name ADD_P VALUE_P opt_if_not_exists Sconst AFTER Sconst' => new AddEnumLabel($name, $flags->present($form->node(5)), $strings->string($form->node(6)), new EnumPosition(true, $strings->string($form->node(8)))),
            'AlterEnumStmt: ALTER TYPE_P any_name RENAME VALUE_P Sconst TO Sconst' => new RenameEnumLabel($name, $strings->string($form->node(5)), $strings->string($form->node(7))),
            'AlterEnumStmt: ALTER TYPE_P any_name DROP VALUE_P Sconst' => new DropEnumLabel($name, $strings->string($form->node(5))),
            'AlterCompositeTypeStmt: ALTER TYPE_P any_name alter_type_cmds' => new AlterComposite($name, $this->changes($form->node(3))),
            'AlterTypeStmt: ALTER TYPE_P any_name SET ( operator_def_list )' => new AlterTypeOptions($name, $this->lowering->routines->operatorDefinitions($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `alter_type_cmds`.
     *
     * @return list<AttributeChange>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function changes(Node $list): array
    {
        $changes = [];
        $flags = $this->lowering->flags;
        foreach ($this->lowering->items($list, 'alter_type_cmds: alter_type_cmd', 'alter_type_cmds: alter_type_cmds , alter_type_cmd') as $item) {
            $form = $this->lowering->productions->form($item);
            $changes[] = match ($form->signature) {
                'alter_type_cmd: ADD_P ATTRIBUTE TableFuncElement opt_drop_behavior' => new AddAttribute($this->lowering->types->typedColumn($form->node(2)), $flags->dropBehavior($form->node(3))),
                'alter_type_cmd: DROP ATTRIBUTE IF_P EXISTS ColId opt_drop_behavior' => new DropAttribute($this->lowering->names->name($form->node(4)), true, $flags->dropBehavior($form->node(5))),
                'alter_type_cmd: DROP ATTRIBUTE ColId opt_drop_behavior' => new DropAttribute($this->lowering->names->name($form->node(2)), false, $flags->dropBehavior($form->node(3))),
                'alter_type_cmd: ALTER ATTRIBUTE ColId opt_set_data TYPE_P Typename opt_collate_clause opt_drop_behavior' => new RetypeAttribute(
                    $this->lowering->names->name($form->node(2)),
                    $this->lowering->types->typeName($form->node(5)),
                    $this->lowering->names->optionalDotted($form->node(6)),
                    $flags->dropBehavior($form->node(7)),
                ),
                default => throw ImplementationGap::production($form),
            };
        }

        return $changes;
    }
}
