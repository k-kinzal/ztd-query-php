<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\AlterOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\CreateOperatorClass;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\CreateOperatorFamily;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\FunctionMember;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberKind;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberPurpose;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberRemoval;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorClassItem;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorFamilyAddition;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorFamilyRemoval;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorMember;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\StorageMember;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers operator classes, operator families and ALTER OPERATOR.
 *
 * Rule: PG-OPCLASS-LOWER-001. Scope: `CreateOpClassStmt`,
 * `opclass_item_list`, `opclass_item`, `opt_opfamily`, `opclass_purpose`,
 * `opt_recheck`, `CreateOpFamilyStmt`, `AlterOpFamilyStmt`,
 * `opclass_drop_list`, `opclass_drop`, `AlterOperatorStmt`,
 * `operator_def_list`, `operator_def_elem`, `operator_def_arg`.
 * Constructors: `CreateOperatorClass`, `CreateOperatorFamily`,
 * `OperatorFamilyAddition`, `OperatorFamilyRemoval`, `AlterOperator`, the
 * class items, `MemberRemoval` and `Definition`. RECHECK is obsolete and
 * ignored by the server; it is a noise word. Termination: lists are
 * flattened iteratively. Source: https://www.postgresql.org/docs/17/sql-createopclass.html,
 * https://www.postgresql.org/docs/17/sql-alteropfamily.html, https://www.postgresql.org/docs/17/sql-alteroperator.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class OperatorClassRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateOpClassStmt`, `CreateOpFamilyStmt`, `AlterOpFamilyStmt` or `AlterOperatorStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'CreateOpClassStmt: CREATE OPERATOR CLASS any_name opt_default FOR TYPE_P Typename USING name opt_opfamily AS opclass_item_list' => new CreateOperatorClass(
                $names->dotted($form->node(3)),
                $this->lowering->types->typeName($form->node(7)),
                $names->name($form->node(9)),
                $this->items($form->node(12)),
                $this->lowering->flags->present($form->node(4)),
                $this->family($form->node(10)),
            ),
            'CreateOpFamilyStmt: CREATE OPERATOR FAMILY any_name USING name' => new CreateOperatorFamily($names->dotted($form->node(3)), $names->name($form->node(5))),
            'AlterOpFamilyStmt: ALTER OPERATOR FAMILY any_name USING name ADD_P opclass_item_list' => new OperatorFamilyAddition($names->dotted($form->node(3)), $names->name($form->node(5)), $this->items($form->node(7))),
            'AlterOpFamilyStmt: ALTER OPERATOR FAMILY any_name USING name DROP opclass_drop_list' => new OperatorFamilyRemoval($names->dotted($form->node(3)), $names->name($form->node(5)), $this->removals($form->node(7))),
            'AlterOperatorStmt: ALTER OPERATOR operator_with_argtypes SET ( operator_def_list )' => new AlterOperator((new SignatureRule($this->lowering))->operator($form->node(2)), $this->definitions($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opclass_item_list`.
     *
     * @return list<OperatorClassItem>
     */
    public function items(Node $list): array
    {
        $items = [];
        foreach ($this->lowering->items($list, 'opclass_item_list: opclass_item', 'opclass_item_list: opclass_item_list , opclass_item') as $item) {
            $items[] = $this->item($item);
        }

        return $items;
    }

    /**
     * Lowers `opclass_item`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function item(Node $item): OperatorClassItem
    {
        $form = $this->lowering->productions->form($item);
        $literals = $this->lowering->literals;
        $signatures = new SignatureRule($this->lowering);

        return match ($form->signature) {
            'opclass_item: OPERATOR Iconst any_operator opclass_purpose opt_recheck' => new OperatorMember($literals->integer($form->node(1)), $this->lowering->operators->operator($form->node(2)), $this->purpose($form->node(3), $form->node(4))),
            'opclass_item: OPERATOR Iconst operator_with_argtypes opclass_purpose opt_recheck' => new OperatorMember($literals->integer($form->node(1)), $signatures->operator($form->node(2)), $this->purpose($form->node(3), $form->node(4))),
            'opclass_item: FUNCTION Iconst function_with_argtypes' => new FunctionMember($literals->integer($form->node(1)), $signatures->function($form->node(2))),
            'opclass_item: FUNCTION Iconst ( type_list ) function_with_argtypes' => new FunctionMember($literals->integer($form->node(1)), $signatures->function($form->node(5)), $this->lowering->types->typeNames($form->node(3))),
            'opclass_item: STORAGE Typename' => new StorageMember($this->lowering->types->typeName($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opclass_purpose`, after checking the `opt_recheck` that follows it; no purpose is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function purpose(Node $purpose, Node $recheck): ?MemberPurpose
    {
        $obsolete = $this->lowering->productions->form($recheck);
        if ($obsolete->signature !== 'opt_recheck: RECHECK' && $obsolete->signature !== 'opt_recheck:') {
            throw ImplementationGap::production($obsolete);
        }
        $form = $this->lowering->productions->form($purpose);

        return match ($form->signature) {
            'opclass_purpose:' => null,
            'opclass_purpose: FOR SEARCH' => new MemberPurpose(),
            'opclass_purpose: FOR ORDER BY any_name' => new MemberPurpose($this->lowering->names->dotted($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_opfamily`; no family is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function family(Node $family): ?DottedName
    {
        $form = $this->lowering->productions->form($family);

        return match ($form->signature) {
            'opt_opfamily:' => null,
            'opt_opfamily: FAMILY any_name' => $this->lowering->names->dotted($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opclass_drop_list`.
     *
     * @return list<MemberRemoval>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function removals(Node $list): array
    {
        $removals = [];
        foreach ($this->lowering->items($list, 'opclass_drop_list: opclass_drop', 'opclass_drop_list: opclass_drop_list , opclass_drop') as $item) {
            $form = $this->lowering->productions->form($item);
            $kind = match ($form->signature) {
                'opclass_drop: OPERATOR Iconst ( type_list )' => MemberKind::Operator,
                'opclass_drop: FUNCTION Iconst ( type_list )' => MemberKind::Function,
                default => throw ImplementationGap::production($form),
            };
            $removals[] = new MemberRemoval($kind, $this->lowering->literals->integer($form->node(1)), $this->lowering->types->typeNames($form->node(3)));
        }

        return $removals;
    }

    /**
     * Lowers `operator_def_list`.
     *
     * @return list<Definition>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definitions(Node $list): array
    {
        $definitions = [];
        foreach ($this->lowering->items($list, 'operator_def_list: operator_def_elem', 'operator_def_list: operator_def_list , operator_def_elem') as $element) {
            $form = $this->lowering->productions->form($element);
            $name = $this->lowering->names->name($form->node(0));
            $definitions[] = match ($form->signature) {
                'operator_def_elem: ColLabel = NONE' => new Definition($name, new KeywordWord(new Name('none'))),
                'operator_def_elem: ColLabel = operator_def_arg' => new Definition($name, $this->argument($form->node(2))),
                'operator_def_elem: ColLabel' => new Definition($name),
                default => throw ImplementationGap::production($form),
            };
        }

        return $definitions;
    }

    /**
     * Lowers `operator_def_arg`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): OptionArgument
    {
        $form = $this->lowering->productions->form($argument);

        return match ($form->signature) {
            'operator_def_arg: func_type' => $this->lowering->types->functionType($form->node(0)),
            'operator_def_arg: reserved_keyword' => new KeywordWord($this->lowering->leaves->record(new Name((new Keywords($this->lowering))->word($form->node(0))))),
            'operator_def_arg: qual_all_Op' => $this->lowering->operators->operator($form->node(0)),
            'operator_def_arg: NumericOnly' => $this->lowering->literals->signed($form->node(0)),
            'operator_def_arg: Sconst' => $this->lowering->literals->string($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
