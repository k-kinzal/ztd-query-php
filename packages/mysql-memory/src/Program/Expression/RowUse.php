<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use WeakReference;

/**
 * Resolves a stored row operand when an instruction requires one value.
 *
 * The program may retain this operand without treating it as a Scalar. Its
 * names and subqueries bind in the current invocation before the width error.
 * No substituted scalar expression or SQL text is needed for preparation.
 *
 * @visibility MySqlMemory
 */
final class RowUse
{
    /**
     * Raises the current binding problem or the scalar-use width condition.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after resolving the row's operands
     */
    public function raise(RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, Session $session, bool $invocation = true): never
    {
        (new \MySqlMemory\Session\Problem\Identifiers())->check($operand, $session->settings()->release());
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        if ($invocation && $session->settings()->release() === GrammarRelease::MySql5651) {
            $session->diagnostics->retain();
        } elseif ($invocation) {
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $derivation->scalar($operand, new Environment($context));
        $problems = $derivation->facts()->diagnostics;
        foreach ($problems as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($problems as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($operand, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        $this->prepare($operand, $planner->compiler, new \MySqlMemory\Evaluation\Scope());
        foreach ($problems as $problem) {
            throw (new Errors())->error($problem, $session);
        }
        throw $this->error($operand);
    }

    /**
     * Prepares row fields in order, including stored calls and subquery plans.
     *
     * No field is evaluated: a scalar use fails after its dependencies bind.
     *
     * @throws \MySqlMemory\Error\SqlError When a field cannot be prepared
     */
    public function prepare(RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubquery) {
            (new QueryUse())->prepare($operand->query, $compiler, $operand->exists);
            $compiler->planner->query($operand->query, $scope);
            return;
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate) {
            (new SubqueryUse())->raise($operand, $compiler, $scope);
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall && !$operand->native($compiler->settings->release())) {
            $compiler->calls->resolve($operand);
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership) {
            $this->membership($operand, $compiler, $scope);
            return;
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
            foreach ($operand->operands() as $part) {
                if ($part instanceof \SqlSemantics\Statement\Scalar || $part instanceof \SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable) {
                    $compiler->compile($part, $scope);
                } else {
                    $this->prepare($part, $compiler, $scope);
                    throw $this->error($part);
                }
            }
            return;
        }

        $query = $compiler->rows->subquery($operand);
        if ($query !== null) {
            $compiler->subqueries->row($query, $scope);
            return;
        }
        foreach ($compiler->rows->elements($operand) ?? [] as $element) {
            if ($element instanceof RowOperand) {
                $this->prepare($element, $compiler, $scope);
            } else {
                $compiler->compile($element, $scope);
            }
        }
    }

    /**
     * Prepares membership in written order, testing each shape after its names bind.
     *
     * @throws \MySqlMemory\Error\SqlError When a dependency or row shape is refused
     */
    public function membership(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership $operand, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        foreach ($operand->operands() as $index => $part) {
            if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
                $this->prepare($part, $compiler, $scope);
                throw $this->error($part);
            }
            if ($part instanceof RowOperand) {
                $this->prepare($part, $compiler, $scope);
            } else {
                $compiler->compile($part, $scope);
            }
            \SqlSemantics\Diagnostic\Check::invariant(!$operand->operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand, 'The deferred first operand would already have raised its condition.');
            $problem = $index === 0 ? null : (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->problem($operand->operand, $part);
            if ($problem !== null) {
                throw QueryError::OperandColumns->error($problem->expected);
            }
        }
    }

    /**
     * Maps the retained intrinsic condition after its dependencies have prepared.
     *
     * @throws LogicException When a deferred condition has no execution implementation
     */
    public function error(RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand): \MySqlMemory\Error\SqlError
    {
        if ($operand instanceof RowOperand) {
            return QueryError::OperandColumns->error(1);
        }
        $problem = $operand->problem();
        return match (true) {
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::EmptyValuesRow => QueryError::ValuesEmptyRow->error(),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse => (new Errors())->misuse($problem),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch => (new Errors())->counted($problem),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse => (new Errors())->write($problem, null),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns => QueryError::OperandColumns->error($problem->expected),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion => $problem->operand ? \MySqlMemory\Error\Family\DataError::TimeZoneCast->error() : \MySqlMemory\Error\Family\DataError::UnknownTimeZone->error($problem->expression->zone->value),
            default => throw new LogicException('An unimplemented deferred operand condition: ' . $problem::class),
        };
    }

}
