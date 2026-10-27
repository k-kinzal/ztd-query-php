<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Write;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Program\Expression\RowUse;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Prepares retained UPDATE operands through the ordinary expression compiler.
 *
 * Legacy multiple-table writes bind destinations before preparing the join and
 * append their observed cleanup condition for errors in the latter phase.
 * Single-table legacy writes prepare ordering before assignments; modern writes
 * prepare it after assignment values. No expression is evaluated or rewritten.
 *
 * @visibility MySqlMemory
 */
final class UpdateOperands
{
    /**
     * Prepares the complete write after its relation dependencies have opened.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound marker values
     * @return bool Whether MySQL 5.6 preparation defers the assignment values
     * @throws SqlError When a reached clause, destination or value cannot prepare
     */
    public function prepare(Update $write, StatementSemantics $facts, Session $session, bool $prepared = false, array $parameters = []): bool
    {
        $rules = new \SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure();
        if ($rules->target($write->tables, $write->assignments, $write->with) !== null) {
            return false;
        }
        $multiple = count((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($write->tables)) > 1;
        $legacy = $session->settings()->legacy();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new Connection($session->variables, $context, $session->user, $session->host, $session->id, $parameters, program: $session->program, session: WeakReference::create($session));
        $planner = new Planner($write, $facts, $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $inputs = new UpdateInputs();
        $scope = $inputs->scope($write->tables, $planner);
        if ($legacy && $multiple) {
            $this->destinations($write, $facts, $session);
            $constraint = $write->orderBy !== [] ? WriteRule::OrderedMultipleUpdate : ($write->limit === null ? null : WriteRule::LimitedMultipleUpdate);
            if ($constraint !== null) {
                throw (new Errors())->error(new WriteMisuse($constraint), $session);
            }
        }
        if ($prepared && $multiple && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            $this->values($write, $planner, $scope);
        }
        try {
            $inputs->conditions($write->tables, $planner, $scope);
            $this->operand($write->where, $planner, $scope, 'where clause');
            $this->ordering($legacy ? $write->orderBy : [], $planner, $scope);
            $this->destinations($write, $facts, $session);
            if ($prepared && $multiple && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
                return true;
            }
            $this->values($write, $planner, $scope);
            $this->ordering($legacy ? [] : $write->orderBy, $planner, $scope);
        } catch (SqlError $error) {
            throw !$prepared && $legacy && $multiple ? new SqlError($error->error, $error->getMessage(), $error, [...$error->following, [StatementError::UnknownError->value, StatementError::UnknownError->message()]], $error->signalled, $error->getCode(), $error->recorded) : $error;
        }
        return false;
    }

    /**
     * Binds the complete assignment list in its selected preparation phase.
     *
     * @throws SqlError When an assignment value cannot bind
     */
    public function values(Update $write, Planner $planner, Scope $scope): void
    {
        foreach ($write->assignments as $assignment) {
            $this->operand($assignment->value, $planner, $scope, 'field list');
        }
    }

    /**
     * Prepares retained ordering with the expression-only UPDATE invariant.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> $orderBy The complete ordering
     * @throws SqlError When an ordering position or operand cannot bind
     */
    public function ordering(array $orderBy, Planner $planner, Scope $scope): void
    {
        foreach ($orderBy as $item) {
            \SqlSemantics\Diagnostic\Check::invariant(!$item->expression instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal, 'UPDATE ordering retains expressions rather than result positions.');
            $position = (new \SqlSemantics\Platform\MySql\Rules\Dml\Read\Ordering())->position($item->expression, $planner->settings->legacy());
            if ($position !== null) {
                throw new SqlError(\MySqlMemory\Error\Family\QueryError::BadField, $position->message());
            }
            $this->operand($item->expression, $planner, $scope, 'order clause');
        }
    }

    /**
     * Reads destination bindings without treating stored variables as columns.
     *
     * @throws SqlError When a destination does not resolve
     */
    public function destinations(Update $write, StatementSemantics $facts, Session $session): void
    {
        foreach ($write->assignments as $assignment) {
            $resolution = $assignment->column->scalarInformation()->resolution;
            if ($resolution instanceof Diagnostic) {
                throw (new Errors())->error($resolution, $session);
            }
        }
    }

    /**
     * Prepares one operand in its original clause and reports retained row shape.
     *
     * @throws SqlError When the operand cannot prepare
     */
    public function operand(Scalar|RowOperand|DeferredOperand|\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest|null $operand, Planner $planner, Scope $scope, string $clause): void
    {
        $scope = clone $scope;
        $scope->clause = $clause;
        if ($operand instanceof Scalar) {
            $planner->compiler->compile($operand, $scope);
        } elseif ($operand instanceof RowOperand || $operand instanceof DeferredOperand) {
            (new RowUse())->prepare($operand, $planner->compiler, $scope);
            throw (new RowUse())->error($operand);
        }
    }
}
