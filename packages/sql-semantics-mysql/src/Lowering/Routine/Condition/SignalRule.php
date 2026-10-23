<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Condition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;

/**
 * Lowers SIGNAL and RESIGNAL.
 *
 * Rule: MYSQL-SIGNAL-LOWERING-001. Scope: signal_stmt, resignal_stmt,
 * signal_value, opt_signal_value, opt_set_signal_information,
 * signal_information_item_list, signal_allowed_expr,
 * signal_condition_information_item_name. The value of an item is a
 * literal, a variable or a name, each lowered by its leaf rule.
 * Constructs: Signal, Resignal, SignalItem. Terminates: the item list is
 * walked with a loop.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html,
 * https://dev.mysql.com/doc/refman/8.4/en/resignal.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SignalRule
{
    /**
     * The condition information items SIGNAL can set.
     */
    private const ITEMS = [
        'signal_condition_information_item_name: CLASS_ORIGIN_SYM' => ConditionItemName::ClassOrigin,
        'signal_condition_information_item_name: SUBCLASS_ORIGIN_SYM' => ConditionItemName::SubclassOrigin,
        'signal_condition_information_item_name: CONSTRAINT_CATALOG_SYM' => ConditionItemName::ConstraintCatalog,
        'signal_condition_information_item_name: CONSTRAINT_SCHEMA_SYM' => ConditionItemName::ConstraintSchema,
        'signal_condition_information_item_name: CONSTRAINT_NAME_SYM' => ConditionItemName::ConstraintName,
        'signal_condition_information_item_name: CATALOG_NAME_SYM' => ConditionItemName::CatalogName,
        'signal_condition_information_item_name: SCHEMA_NAME_SYM' => ConditionItemName::SchemaName,
        'signal_condition_information_item_name: TABLE_NAME_SYM' => ConditionItemName::TableName,
        'signal_condition_information_item_name: COLUMN_NAME_SYM' => ConditionItemName::ColumnName,
        'signal_condition_information_item_name: CURSOR_NAME_SYM' => ConditionItemName::CursorName,
        'signal_condition_information_item_name: MESSAGE_TEXT_SYM' => ConditionItemName::MessageText,
        'signal_condition_information_item_name: MYSQL_ERRNO_SYM' => ConditionItemName::MysqlErrno,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers SIGNAL or RESIGNAL: the form of `signal_stmt` or `resignal_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Signal|Resignal
    {
        if ($form->signature === 'signal_stmt: SIGNAL_SYM signal_value opt_set_signal_information') {
            $condition = $this->condition($form->node(1));

            return new Signal($condition, $this->items($form->node(2), $condition));
        }
        if ($form->signature !== 'resignal_stmt: RESIGNAL_SYM opt_signal_value opt_set_signal_information') {
            throw ImplementationGap::production($form);
        }
        $value = $this->lowering->form($form->node(1));

        $condition = match ($value->signature) {
            'opt_signal_value:' => null,
            'opt_signal_value: signal_value' => $this->condition($value->node(0)),
            default => throw ImplementationGap::production($value),
        };

        return new Resignal($condition, $this->items($form->node(2), $condition));
    }

    /**
     * Lowers the condition of SIGNAL or RESIGNAL: a node of `signal_value`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function condition(Node $value): ConditionName|SqlState
    {
        $form = $this->lowering->form($value);
        if ($form->signature === 'signal_value: ident') {
            return $this->lowering->origins->record(new ConditionName($this->lowering->names->identifier($form->node(0))), $value);
        }
        $state = $form->signature === 'signal_value: sqlstate' ? (new ConditionRule($this->lowering))->value($form->node(0)) : null;

        return $state instanceof SqlState ? $state : throw ImplementationGap::production($form);
    }

    /**
     * Lowers the SET clause: a node of `opt_set_signal_information`; an absent clause is empty.
     *
     * @return list<SignalItem>
     * @throws \SqlSemantics\Construction\RejectedInput When an item operand is outside its value domain
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $clause, ConditionName|SqlState|null $condition = null): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_set_signal_information:') {
            return [];
        }
        if ($clause->name !== 'signal_information_item_list' && $form->signature !== 'opt_set_signal_information: SET signal_information_item_list' && $form->signature !== 'opt_set_signal_information: SET_SYM signal_information_item_list') {
            throw ImplementationGap::production($form);
        }
        $pending = [];
        $list = $clause->name === 'signal_information_item_list' ? $form : $this->lowering->form($form->node(1));
        while (true) {
            $first = match ($list->signature) {
                'signal_information_item_list: signal_condition_information_item_name EQ signal_allowed_expr' => 0,
                'signal_information_item_list: signal_information_item_list , signal_condition_information_item_name EQ signal_allowed_expr' => 2,
                default => throw ImplementationGap::production($list),
            };
            $pending[] = [$list, $first];
            if ($first === 0) {
                break;
            }
            $list = $this->lowering->form($list->node(0));
        }
        $items = [];
        foreach (array_reverse($pending) as [$item, $offset]) {
            $name = $this->lowering->form($item->node($offset));
            try {
                $candidate = new SignalItem(self::ITEMS[$name->signature] ?? throw ImplementationGap::production($name), $this->operand($item->node($offset + 2), $clause));
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                if (!$failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral) {
                    throw $failure;
                }
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems(new Resignal($condition, $items), $failure->problem), $failure->end);
            }
            if (in_array($candidate->name, array_column($items, 'name'), true)) {
                $tokens = $item->node($offset + 2)->tokens();
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems(new Resignal($condition, $items), $candidate), $tokens[count($tokens) - 1]->end());
            }
            $items[] = $candidate;
        }

        return $items;
    }

    /**
     * Lowers the value of an item or a condition number: a node of `signal_allowed_expr`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When a literal is outside its value domain
     * @throws \SqlSemantics\Diagnostic\AnalysisException When the operand assigns a variable instead of reading it
     */
    public function operand(Node $expression, ?Node $container = null): LiteralExpression|ColumnUse|SystemVariable|UserVariable
    {
        $form = $this->lowering->form($expression);

        $value = match ($form->signature) {
            'signal_allowed_expr: literal', 'signal_allowed_expr: literal_or_null' => $this->lowering->literals->literal($form->node(0)),
            'signal_allowed_expr: variable', 'signal_allowed_expr: rvalue_system_or_user_variable' => $this->lowering->variables->variable($form->node(0)),
            'signal_allowed_expr: simple_ident' => $this->lowering->names->column($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
        if ($value instanceof VariableAssignment || $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment) {
            $tokens = $expression->tokens();
            $end = $tokens[count($tokens) - 1]->end();
            $at = new \SqlParser\Lexer\Token(0, '$end', '', $end);
            foreach ($container === null ? [] : $container->tokens() as $token) {
                if ($token->offset >= $end) {
                    $at = $token->text === ',' ? new \SqlParser\Lexer\Token(0, '$end', '', $token->end()) : $token;
                    break;
                }
            }
            throw new \SqlSemantics\Diagnostic\AnalysisException('Syntax error: a condition operand reads a variable; it cannot assign one.', 0, new \SqlParser\Parser\SyntaxException($at, [], ''));
        }

        return $value;
    }
}
