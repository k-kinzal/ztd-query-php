<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Condition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;

/**
 * Lowers condition values: error codes, SQLSTATE values, condition names and condition classes.
 *
 * Rule: MYSQL-CONDITION-LOWERING-001. Scope: sp_cond, sqlstate, opt_value,
 * sp_hcond, sp_hcond_element, sp_hcond_list. The optional word VALUE holds
 * no operand. Constructs: ErrorCode, SqlState, ConditionName,
 * GeneralCondition. Terminates: the list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html,
 * https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ConditionRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an error code or an SQLSTATE value: a node of `sp_cond` or `sqlstate`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Node $condition): ErrorCode|SqlState
    {
        $form = $this->lowering->form($condition);
        if ($form->signature === 'sp_cond: sqlstate') {
            $form = $this->lowering->form($form->node(0));
        }
        if ($form->signature === 'sp_cond: ulong_num') {
            return new ErrorCode($this->lowering->numbers->numeral($form->node(0)));
        }
        if ($form->signature !== 'sqlstate: SQLSTATE_SYM opt_value TEXT_STRING_literal') {
            throw ImplementationGap::production($form);
        }
        $word = $this->lowering->form($form->node(1));
        if ($word->signature !== 'opt_value:' && $word->signature !== 'opt_value: VALUE_SYM') {
            throw ImplementationGap::production($word);
        }

        return new SqlState($this->lowering->literals->text($form->node(2)));
    }

    /**
     * Lowers the conditions of a handler: a node of `sp_hcond_list`.
     *
     * @return list<Condition>
     * @throws ImplementationGap When a production has no rule
     */
    public function handled(Node $list): array
    {
        $conditions = [];
        foreach ((new Sequence($this->lowering))->items($list, ['sp_hcond_list: sp_hcond_element', 'sp_hcond_list: sp_hcond_list , sp_hcond_element']) as $item) {
            $element = $this->lowering->form($item);
            if ($element->signature !== 'sp_hcond_element: sp_hcond') {
                throw ImplementationGap::production($element);
            }
            $form = $this->lowering->form($element->node(0));
            if ($form->signature === 'sp_hcond: not FOUND_SYM') {
                $this->lowering->options->skip($form->node(0));
            }
            $conditions[] = match ($form->signature) {
                'sp_hcond: sp_cond' => $this->value($form->node(0)),
                'sp_hcond: ident' => new ConditionName($this->lowering->names->identifier($form->node(0))),
                'sp_hcond: SQLWARNING_SYM' => new GeneralCondition(ConditionClass::SqlWarning),
                'sp_hcond: not FOUND_SYM' => new GeneralCondition(ConditionClass::NotFound),
                'sp_hcond: SQLEXCEPTION_SYM' => new GeneralCondition(ConditionClass::SqlException),
                default => throw ImplementationGap::production($form),
            };
        }

        return $conditions;
    }
}
