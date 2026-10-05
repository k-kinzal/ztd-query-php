<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the clauses written after a query: LIMIT, PROCEDURE ANALYSE, INTO and the locking clauses.
 *
 * Rule: MYSQL-QUERY-TAIL-001. Scope: opt_limit_clause, opt_limit_clause_init,
 * limit_clause, limit_options, limit_option, opt_simple_limit,
 * procedure_analyse_clause, opt_procedure_analyse_clause,
 * opt_procedure_analyse_params, procedure_analyse_param, into, into_clause,
 * opt_into, into_destination, select_var_list_init, select_var_list,
 * select_var_ident, select_lock_type, opt_select_lock_type,
 * locking_clause_list, locking_clause, lock_strength, table_locking_list,
 * opt_locked_row_action, locked_row_action. A LIMIT operand is an unsigned
 * integer, a parameter marker or a stored program variable; an INTO target
 * written with `@` is a user variable, a bare one a stored program variable.
 * Constructs: RowLimit, ProcedureAnalyse, IntoVariables, IntoOutfile,
 * IntoDumpfile, ProgramVariable, LockingClause. Terminates: lists are
 * flattened iteratively. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class TailRule
{
    /**
     * The productions of a locked row action.
     */
    private const ACTIONS = ['opt_locked_row_action:' => null, 'locked_row_action: SKIP_SYM LOCKED_SYM' => LockedRowAction::SkipLocked, 'locked_row_action: NOWAIT_SYM' => LockedRowAction::Nowait];

    /**
     * The productions of the 5.x locking clause; null when absent.
     */
    private const LEGACY_LOCKS = [
        'select_lock_type:' => null, 'opt_select_lock_type:' => null, 'select_lock_type: FOR_SYM UPDATE_SYM' => LockStrength::Update,
        'opt_select_lock_type: FOR_SYM UPDATE_SYM' => LockStrength::Update, 'select_lock_type: LOCK_SYM IN_SYM SHARE_SYM MODE_SYM' => LockStrength::ShareMode,
        'opt_select_lock_type: LOCK_SYM IN_SYM SHARE_SYM MODE_SYM' => LockStrength::ShareMode,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a LIMIT clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function limit(Node $clause): ?RowLimit
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_limit_clause:', 'opt_limit_clause_init:', 'opt_simple_limit:' => null,
            'opt_limit_clause: limit_clause', 'opt_limit_clause_init: limit_clause' => $this->limit($form->node(0)),
            'limit_clause: LIMIT limit_options' => $this->options($form->node(1)),
            'opt_simple_limit: LIMIT limit_option' => new RowLimit($this->value($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the operands of a LIMIT clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): RowLimit
    {
        $form = $this->lowering->form($options);

        return match ($form->signature) {
            'limit_options: limit_option' => new RowLimit($this->value($form->node(0))),
            'limit_options: limit_option , limit_option' => new RowLimit($this->value($form->node(2)), $this->value($form->node(0)), OffsetSpelling::Comma),
            'limit_options: limit_option OFFSET_SYM limit_option' => new RowLimit($this->value($form->node(0)), $this->value($form->node(2)), OffsetSpelling::Keyword),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers one LIMIT operand.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function value(Node $option): Scalar
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'limit_option: ident' => new ProgramVariable($this->lowering->names->identifier($form->node(0))),
            'limit_option: param_marker' => $this->lowering->literals->parameter($form->node(0)),
            'limit_option: ULONGLONG_NUM', 'limit_option: LONG_NUM', 'limit_option: NUM' => $this->lowering->leaves->record(new NumberLiteral($form->token(0)->text)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a PROCEDURE ANALYSE clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function procedure(Node $clause): ?ProcedureAnalyse
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'procedure_analyse_clause:' || $form->signature === 'opt_procedure_analyse_clause:') {
            return null;
        }
        if ($form->signature !== 'procedure_analyse_clause: PROCEDURE_SYM ANALYSE_SYM ( opt_procedure_analyse_params )' && $form->signature !== 'opt_procedure_analyse_clause: PROCEDURE_SYM ANALYSE_SYM ( opt_procedure_analyse_params )') {
            throw ImplementationGap::production($form);
        }
        $params = $this->lowering->form($form->node(3));
        $positions = match ($params->signature) {
            'opt_procedure_analyse_params:' => [],
            'opt_procedure_analyse_params: procedure_analyse_param' => [0],
            'opt_procedure_analyse_params: procedure_analyse_param , procedure_analyse_param' => [0, 2],
            default => throw ImplementationGap::production($params),
        };
        $arguments = [];
        foreach ($positions as $position) {
            $param = $this->lowering->form($params->node($position));
            if ($param->signature !== 'procedure_analyse_param: NUM') {
                throw ImplementationGap::production($param);
            }
            $arguments[] = $this->lowering->leaves->record(new NumberLiteral($param->token(0)->text));
        }

        return new ProcedureAnalyse($arguments);
    }

    /**
     * Lowers an INTO clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function into(Node $clause): ?IntoDestination
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_into:' => null,
            'opt_into: into' => $this->into($form->node(0)),
            'into: INTO into_destination', 'into_clause: INTO into_destination' => $this->destination($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the destination of an INTO clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function destination(Node $destination): IntoDestination
    {
        $form = $this->lowering->form($destination);

        return match ($form->signature) {
            'into_destination: OUTFILE TEXT_STRING_filesystem opt_load_data_charset opt_field_term opt_line_term' => new IntoOutfile($this->lowering->literals->text($form->node(1)), $this->lowering->dml->fileFormat($form->node(2), $form->node(3), $form->node(4))),
            'into_destination: DUMPFILE TEXT_STRING_filesystem' => new IntoDumpfile($this->lowering->literals->text($form->node(1))),
            'into_destination: select_var_list_init' => $this->destination($form->node(0)),
            'select_var_list_init: select_var_list', 'into_destination: select_var_list' => $this->variables($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the variables of an INTO clause in written order.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function variables(Node $list): IntoVariables
    {
        $form = $this->lowering->form($list);
        if ($form->signature !== 'select_var_list: select_var_list , select_var_ident' && $form->signature !== 'select_var_list: select_var_ident') {
            throw ImplementationGap::production($form);
        }
        $targets = [];
        foreach ((new Lists())->items($list) as $item) {
            $target = $this->lowering->form($item);
            $targets[] = match ($target->signature) {
                'select_var_ident: @ ident_or_text' => $this->lowering->variables->user($target->node(1)),
                'select_var_ident: ident_or_text' => new ProgramVariable($this->lowering->names->identifier($target->node(0))),
                default => throw ImplementationGap::production($target),
            };
        }

        return new IntoVariables($targets);
    }

    /**
     * Lowers the locking clause of the 5.x grammars or the locking clause list of 8.0 and later; an absent clause is empty.
     *
     * @return list<LockingClause>
     * @throws ImplementationGap When a production has no rule
     */
    public function locking(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if (array_key_exists($form->signature, self::LEGACY_LOCKS)) {
            $strength = self::LEGACY_LOCKS[$form->signature];

            return $strength === null ? [] : [new LockingClause($strength)];
        }
        if ($form->signature !== 'locking_clause_list: locking_clause_list locking_clause' && $form->signature !== 'locking_clause_list: locking_clause') {
            throw ImplementationGap::production($form);
        }
        $clauses = [];
        foreach ((new Lists())->items($clause) as $item) {
            $clauses[] = $this->lock($item);
        }

        return $clauses;
    }

    /**
     * Lowers one locking clause of 8.0 and later.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lock(Node $clause): LockingClause
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'locking_clause: LOCK_SYM IN_SYM SHARE_SYM MODE_SYM') {
            return new LockingClause(LockStrength::ShareMode);
        }
        $tables = [];
        $wildcards = [];
        if ($form->signature === 'locking_clause: FOR_SYM lock_strength table_locking_list opt_locked_row_action') {
            $list = $this->lowering->form($form->node(2));
            if ($list->signature !== 'table_locking_list: OF_SYM table_alias_ref_list') {
                throw ImplementationGap::production($list);
            }
            $tables = $this->lowering->dml->deleteTargets($list->node(1));
            $wildcards = $this->lowering->dml->wildcards($list->node(1));
        } elseif ($form->signature !== 'locking_clause: FOR_SYM lock_strength opt_locked_row_action') {
            throw ImplementationGap::production($form);
        }
        $strength = $this->lowering->form($form->node(1));
        $action = $this->lowering->form($form->node(count($form->node->children) - 1));
        if ($action->signature === 'opt_locked_row_action: locked_row_action') {
            $action = $this->lowering->form($action->node(0));
        }
        if (!array_key_exists($action->signature, self::ACTIONS)) {
            throw ImplementationGap::production($action);
        }

        return new LockingClause(match ($strength->signature) {
            'lock_strength: UPDATE_SYM' => LockStrength::Update,
            'lock_strength: SHARE_SYM' => LockStrength::Share,
            default => throw ImplementationGap::production($strength),
        }, $tables, self::ACTIONS[$action->signature], $wildcards);
    }
}
