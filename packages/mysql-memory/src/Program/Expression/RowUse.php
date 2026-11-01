<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
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
        $operand = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($operand);
        (new \MySqlMemory\Session\Problem\Identifiers())->check($operand, $session->settings()->release());
        if ($invocation && $session->settings()->release() === GrammarRelease::MySql5651) {
            $session->diagnostics->retain();
        } elseif ($invocation) {
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = (new \MySqlMemory\Session\Resolution\Parameters())->derivation($operand, $context, ($session->preparation->resolution->parameters ?? []) + array_map(static fn (\MySqlMemory\Typing\Domain $domain): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain => $domain->resolved(), $session->preparation->domains));
        $derivation->scalar($operand, $derivation->environment());
        $problems = (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation)->diagnostics;
        (new \MySqlMemory\Session\Access\RelationEntry())->check($operand, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session);
        foreach ($problems as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($operand, $session);
        foreach ($problems as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($operand, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
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
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate) {
            $this->aggregate($operand, $compiler, $scope);
            return;
        }
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
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate) {
            $this->window($operand, $compiler, $scope);
            return;
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
            $this->deferred($operand, $compiler, $scope);
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
     * Prepares deferred arguments and preserves MATCH's following AGAINST condition.
     *
     * @throws \MySqlMemory\Error\SqlError When a dependency fails preparation
     */
    public function deferred(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredGroupConcat || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate) {
            $scope = clone $scope;
            if ($operand->over === null) {
                $scope->grouping = null;
            }
            $scope->windowing = null;
        }
        try {
            foreach ($operand->operands() as $part) {
                if ($part instanceof \SqlSemantics\Statement\Scalar || $part instanceof \SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable) {
                    if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone && $part instanceof \SqlSemantics\Statement\Scalar) {
                        $compiler->timestamp($part, $scope);
                    } else {
                        $compiler->compile($part, $scope);
                    }
                } else {
                    $this->prepare($part, $compiler, $scope);
                    if (!$this->pending($part, $scope)) {
                        throw $this->error($part);
                    }
                }
            }
        } catch (\MySqlMemory\Error\SqlError $error) {
            if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch) {
                $following = \MySqlMemory\Error\Family\StatementError::WrongArguments;
                throw new \MySqlMemory\Error\SqlError($error->error, $error->getMessage(), $error, [...$error->following, [$following->value, $following->message('AGAINST')]]);
            }
            throw $error;
        }
    }

    /**
     * Prepares a complete window call, retaining its option condition until window resolution.
     *
     * @throws \MySqlMemory\Error\SqlError When a call is misplaced or an argument cannot prepare
     */
    public function window(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate $call, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        $windows = $scope->windowing;
        if (!$windows instanceof \MySqlMemory\Plan\Window\Collected) {
            if ($call->over instanceof \SqlSemantics\Statement\Identifier\Name && array_filter($scope->windowNames, static fn ($name): bool => strcasecmp($name->value, $call->over->value) === 0) === []) {
                throw QueryError::WindowNotDefined->error($call->over->value);
            }
            throw QueryError::WindowFunctionMisplaced->error($call instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction ? strtolower($call->kind->value) : strtolower($call->function->value));
        }
        if ($call->over instanceof \SqlSemantics\Statement\Identifier\Name) {
            $windows->named($call->over);
        }
        $input = clone $scope;
        $input->windowing = null;
        foreach ($call->operands() as $argument) {
            if ($argument instanceof RowOperand || $argument instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
                $this->prepare($argument, $compiler, $input);
                throw $this->error($argument);
            }
            $compiler->compile($argument, $input);
        }
        if ($call instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate && $call->order !== []) {
            $scope->clause = 'order clause';
        }
        $windows->calls[] = [$call, []];
    }

    /**
     * Resolves aggregate placement and window identity before non-scalar arguments.
     *
     * @throws \MySqlMemory\Error\SqlError When the call or an argument cannot prepare
     */
    public function aggregate(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate $call, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        if ($call->over !== null) {
            if ($scope->windowing === null) {
                if ($call->over instanceof \SqlSemantics\Statement\Identifier\Name && array_filter($scope->windowNames, static fn ($name): bool => strcasecmp($name->value, $call->over->value) === 0) === []) {
                    throw QueryError::WindowNotDefined->error($call->over->value);
                }
                throw QueryError::WindowFunctionMisplaced->error(strtolower($call->function->value));
            }
            if ($call->over instanceof \SqlSemantics\Statement\Identifier\Name) {
                $scope->windowing->named($call->over);
            }
        } elseif ($scope->grouping === null) {
            throw QueryError::InvalidGroupFunctionUse->error();
        }
        $input = clone $scope;
        $input->grouping = null;
        $input->windowing = null;
        $this->deferred($call, $compiler, $input);
    }

    /**
     * Answers whether the reached condition belongs to the query's later window phase.
     */
    public function pending(RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, \MySqlMemory\Evaluation\Scope $scope): bool
    {
        while ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Constructor\DeferredRow) {
            return false;
        }
        return $scope->windowing instanceof \MySqlMemory\Plan\Window\Collected && $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand && ($operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing || $operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount || $operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint || $operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement || $operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint || $operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint || $operand->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint);
    }

    /**
     * Prepares membership in written order, testing each shape after its names bind.
     *
     * @throws \MySqlMemory\Error\SqlError When a dependency or row shape is refused
     */
    public function membership(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership $operand, \MySqlMemory\Evaluation\Compile\Compiler $compiler, \MySqlMemory\Evaluation\Scope $scope): void
    {
        $shapes = new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes();
        $first = null;
        foreach ($operand->operands() as $index => $part) {
            if ($part instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
                $this->prepare($part, $compiler, $scope);
                if (!$this->pending($part, $scope)) {
                    throw $this->error($part);
                }
                $shape = [];
            } else {
                if ($part instanceof RowOperand) {
                    $this->prepare($part, $compiler, $scope);
                } else {
                    $compiler->compile($part, $scope);
                }
                $shape = $shapes->parts($part);
            }
            $problem = $index === 0 ? null : $shapes->pairs($first, $shape);
            if ($problem !== null) {
                throw QueryError::OperandColumns->error($problem->expected);
            }
            if ($index === 0) {
                $first = $shape;
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
        while ($operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof RowOperand || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Constructor\DeferredRow) {
            return QueryError::OperandColumns->error(1);
        }
        $problem = $operand->problem();
        return (new \MySqlMemory\Session\Problem\CallErrors())->error($problem) ?? match (true) {
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::EmptyValuesRow => QueryError::ValuesEmptyRow->error(),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse => (new Errors())->misuse($problem),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision => \MySqlMemory\Error\Family\SchemaError::TooBigPrecision->error($problem->precision, $problem->function, $problem->maximum),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch => (new Errors())->counted($problem),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse => (new Errors())->write($problem, null),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns => QueryError::OperandColumns->error($problem->expected),
            $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion => $problem->operand ? \MySqlMemory\Error\Family\DataError::TimeZoneCast->error() : \MySqlMemory\Error\Family\DataError::UnknownTimeZone->error($problem->expression->zone->value),
            default => throw new LogicException('An unimplemented deferred operand condition: ' . $problem::class),
        };
    }

}
