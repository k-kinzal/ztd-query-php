<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Condition;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\ProgramErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Temporal;
use SqlParser\Parser\Node as Tree;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;

/**
 * Raises the problems the server finds while it parses SIGNAL and RESIGNAL, in the order they are written.
 *
 * The condition comes first: a condition name no handler scope declares (ER_SP_COND_MISMATCH),
 * an SQLSTATE of the wrong form or of class '00' (ER_SP_BAD_SQLSTATE), or a condition that is
 * not an SQLSTATE. Then each information item in turn: an invalid temporal literal
 * (ER_WRONG_VALUE) or an unknown system variable in its value, in written order, then the
 * item named twice (ER_DUP_SIGNAL_SET). All come before RESIGNAL outside a handler and before
 * the names of the values are resolved (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0
 * servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html,
 * https://dev.mysql.com/doc/refman/8.4/en/resignal.html.
 *
 * @visibility MySqlMemory
 */
final class SignalProblems
{
    /**
     * Tells whether a statement is SIGNAL or RESIGNAL, whose temporal literals are checked with its other parse-time problems rather than first.
     */
    public function signals(Tree $tree): bool
    {
        return in_array($tree->tokens()[0]->name ?? '', ['SIGNAL_SYM', 'RESIGNAL_SYM'], true);
    }

    /**
     * Raises the first parse-time problem of a SIGNAL or RESIGNAL statement.
     *
     * @throws SqlError When the statement has such a problem
     */
    public function check(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        if (!$statement instanceof Signal && !$statement instanceof Resignal) {
            return;
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof ProgramProblem && in_array($diagnostic->rule, [ProgramRule::UndefinedCondition, ProgramRule::BadSqlState, ProgramRule::SignalConditionKind], true)) {
                throw (new ProgramErrors())->error($diagnostic);
            }
        }
        $seen = [];
        foreach ($statement->items as $item) {
            foreach ((new Walker())->find($item->value, Node::class) as $node) {
                $this->value($node, $operation, $session);
            }
            if (in_array($item->name, $seen, true)) {
                throw (new ProgramErrors())->error(new ProgramProblem(ProgramRule::DuplicateSignalItem, $item->name->value));
            }
            $seen[] = $item->name;
        }
    }

    /**
     * Raises the problem of one node of the value of an item: an invalid temporal literal, or a system variable the server does not know.
     *
     * @throws SqlError When the node has such a problem
     */
    public function value(Node $node, Operation $operation, Session $session): void
    {
        if ($node instanceof TemporalLiteral) {
            $form = $node->form === TemporalForm::Timestamp ? 'DATETIME' : $node->form->value;
            $modes = $session->modes();
            if (Temporal::literal($form, $node->text, 6, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE')) === null) {
                throw DataError::WrongValue->error($form, substr($node->text, 0, 128));
            }
        }
        if (!$node instanceof SystemVariable) {
            return;
        }
        $name = strtolower(($node->instance === null ? '' : $node->instance->value . '.') . $node->name->value);
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownSystemVariable && strtolower($diagnostic->name) === $name) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
    }
}
