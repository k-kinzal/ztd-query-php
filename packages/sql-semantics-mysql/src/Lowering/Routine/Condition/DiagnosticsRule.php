<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Condition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\ConditionDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\DiagnosticsArea;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;

/**
 * Lowers GET DIAGNOSTICS.
 *
 * Rule: MYSQL-DIAGNOSTICS-LOWERING-001. Scope: get_diagnostics, which_area,
 * diagnostics_information, statement_information,
 * statement_information_item, statement_information_item_name,
 * simple_target_specification, condition_number, condition_information,
 * condition_information_item, condition_information_item_name. Constructs:
 * GetDiagnostics, StatementDiagnostics, ConditionDiagnostics,
 * InformationItem. Terminates: the lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DiagnosticsRule
{
    /**
     * The area keywords.
     */
    private const AREAS = ['which_area:' => null, 'which_area: CURRENT_SYM' => DiagnosticsArea::Current, 'which_area: STACKED_SYM' => DiagnosticsArea::Stacked];

    /**
     * The information item names.
     */
    private const NAMES = [
        'statement_information_item_name: NUMBER_SYM' => StatementItemName::Number, 'statement_information_item_name: ROW_COUNT_SYM' => StatementItemName::RowCount,
        'condition_information_item_name: CLASS_ORIGIN_SYM' => ConditionItemName::ClassOrigin,
        'condition_information_item_name: SUBCLASS_ORIGIN_SYM' => ConditionItemName::SubclassOrigin,
        'condition_information_item_name: CONSTRAINT_CATALOG_SYM' => ConditionItemName::ConstraintCatalog,
        'condition_information_item_name: CONSTRAINT_SCHEMA_SYM' => ConditionItemName::ConstraintSchema,
        'condition_information_item_name: CONSTRAINT_NAME_SYM' => ConditionItemName::ConstraintName,
        'condition_information_item_name: CATALOG_NAME_SYM' => ConditionItemName::CatalogName,
        'condition_information_item_name: SCHEMA_NAME_SYM' => ConditionItemName::SchemaName,
        'condition_information_item_name: TABLE_NAME_SYM' => ConditionItemName::TableName,
        'condition_information_item_name: COLUMN_NAME_SYM' => ConditionItemName::ColumnName,
        'condition_information_item_name: CURSOR_NAME_SYM' => ConditionItemName::CursorName,
        'condition_information_item_name: MESSAGE_TEXT_SYM' => ConditionItemName::MessageText,
        'condition_information_item_name: MYSQL_ERRNO_SYM' => ConditionItemName::MysqlErrno,
        'condition_information_item_name: RETURNED_SQLSTATE_SYM' => ConditionItemName::ReturnedSqlstate,
    ];

    /**
     * The information list productions.
     */
    private const LISTS = [
        'statement_information: statement_information_item', 'statement_information: statement_information , statement_information_item',
        'condition_information: condition_information_item', 'condition_information: condition_information , condition_information_item',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers GET DIAGNOSTICS: the form of `get_diagnostics`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): GetDiagnostics
    {
        if ($form->signature !== 'get_diagnostics: GET_SYM which_area DIAGNOSTICS_SYM diagnostics_information') {
            throw ImplementationGap::production($form);
        }
        $area = $this->lowering->form($form->node(1));
        if (!array_key_exists($area->signature, self::AREAS)) {
            throw ImplementationGap::production($area);
        }
        $information = $this->lowering->form($form->node(3));
        if ($information->signature === 'diagnostics_information: statement_information') {
            return new GetDiagnostics(new StatementDiagnostics($this->items($information->node(0))), self::AREAS[$area->signature]);
        }
        if ($information->signature !== 'diagnostics_information: CONDITION_SYM condition_number condition_information') {
            throw ImplementationGap::production($information);
        }
        $number = $this->lowering->form($information->node(1));
        if ($number->signature !== 'condition_number: signal_allowed_expr') {
            throw ImplementationGap::production($number);
        }

        return new GetDiagnostics(new ConditionDiagnostics((new SignalRule($this->lowering))->operand($number->node(0)), $this->items($information->node(2))), self::AREAS[$area->signature]);
    }

    /**
     * Lowers the assignments: a node of `statement_information` or `condition_information`.
     *
     * @return list<InformationItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list): array
    {
        $items = [];
        foreach ((new Sequence($this->lowering))->items($list, self::LISTS) as $item) {
            $form = $this->lowering->form($item);
            if ($form->signature !== 'statement_information_item: simple_target_specification EQ statement_information_item_name' && $form->signature !== 'condition_information_item: simple_target_specification EQ condition_information_item_name') {
                throw ImplementationGap::production($form);
            }
            $target = $this->lowering->form($form->node(0));
            $name = $this->lowering->form($form->node(2));
            $items[] = new InformationItem(match ($target->signature) {
                'simple_target_specification: ident' => $this->lowering->names->identifier($target->node(0)),
                'simple_target_specification: @ ident_or_text' => $this->lowering->variables->user($target->node(1)),
                default => throw ImplementationGap::production($target),
            }, self::NAMES[$name->signature] ?? throw ImplementationGap::production($name));
        }

        return $items;
    }
}
