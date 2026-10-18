<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Scalar;

/**
 * Collects window dependencies while preparing a selection that will refuse a later operand.
 *
 * Arguments and a directly named window bind at the call. Inline specifications,
 * options and frames follow all ordinary clauses. No rows are evaluated before
 * that preparation completes; the retained refusal prevents this partial plan
 * from executing.
 *
 * @visibility MySqlMemory
 */
final class Collected
{
    /**
     * @var list<array{WindowFunction|Aggregate|GroupConcat|JsonObjectAggregate|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate, list<Evaluable>}> Calls and their prepared arguments
     */
    public array $calls = [];

    /**
     * @param Planner $planner The statement's compiler and bound facts
     * @param int $offset The first window output slot
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition> $windows The selection's complete named definitions
     */
    public function __construct(public readonly Planner $planner, public readonly int $offset, public readonly array $windows = [])
    {
    }

    /**
     * Resolves a reached call's input without moving its option checks before a later clause.
     */
    public function compile(Scalar $call, Scope $scope): Evaluable
    {
        \SqlSemantics\Diagnostic\Check::invariant($call instanceof WindowFunction || $call instanceof Aggregate || $call instanceof GroupConcat || $call instanceof JsonObjectAggregate, 'A collected window retains its semantic function call.');
        if ($call->over instanceof Name) {
            $this->named($call->over);
        }
        $input = clone $scope;
        $input->windowing = null;
        $arguments = $call instanceof JsonObjectAggregate ? [$call->key, $call->value] : $call->arguments;
        $compiler = $this->planner->compiler;
        $prepared = array_map(static fn (Operand $argument): Evaluable => $compiler->compile($argument, $input), $arguments);
        $read = new ColumnRead($compiler->domain($call), $this->offset + count($this->calls));
        $this->calls[] = [$call, $prepared];
        $scope->bind($call, $read);
        return $read;
    }

    /**
     * Checks all retained window dependencies before reporting the first pending option.
     *
     * @throws \MySqlMemory\Error\SqlError When a window dependency or option is refused
     */
    public function finish(Scope $scope): void
    {
        $calls = array_column($this->calls, 0);
        $references = array_map(static fn ($call): Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification => $call->over ?? throw new LogicException('A collected window call retains its OVER clause.'), $calls);
        $input = clone $scope;
        $input->windowing = null;
        $specifications = [...array_map(static fn ($window): array => [$window->name->value, $window->specification], $this->windows), ...array_map(static fn ($over): array => [Specification::UNNAMED, $over], array_values(array_filter($references, static fn ($over): bool => !$over instanceof Name)))];
        $this->dependencies($specifications, $input);
        $windows = (new Resolution())->windows($this->windows, $references);
        $this->options();
        foreach ($windows as $window) {
            $used = array_filter($references, static fn ($over): bool => $over instanceof Name ? strcasecmp($over->value, $window->name) === 0 : $over === $window->node) !== [];
            (new Frames($this->planner))->check($window, $input, $used);
        }
        foreach ($calls as $call) {
            if ($call instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction) {
                throw \MySqlMemory\Error\Family\StatementError::WrongArguments->error(strtolower($call->kind->value));
            }
            if ($call instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate) {
                $problem = $call->problem();
                throw $problem instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing ? \MySqlMemory\Error\Family\StatementError::NotSupportedYet->error($problem->limit->value) : QueryError::WindowFrameIllegal->error(Specification::UNNAMED);
            }
            (new Functions($this->planner))->compile($call, $input);
        }
    }

    /**
     * Prepares every retained partitioning and ordering item in native clause order.
     *
     * @param list<array{string, \SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification}> $specifications Named definitions followed by inline specifications
     * @throws \MySqlMemory\Error\SqlError When a reached item cannot prepare
     */
    public function dependencies(array $specifications, Scope $input): void
    {
        foreach ($specifications as [$name, $specification]) {
            \SqlSemantics\Diagnostic\Check::invariant($specification instanceof \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec || $specification instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec, 'A MySQL window retains its complete specification.');
            foreach (['window partition by' => $specification->partition, 'window order by' => $specification->order] as $clause => $items) {
                foreach ($items as $item) {
                    if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Query\OrderItem) {
                        (new Resolution())->item($name, $item);
                    }
                    (new \MySqlMemory\Evaluation\Compile\Selections())->operand($item->expression, $this->planner->compiler, $input->inClause($clause));
                }
            }
        }
    }

    /**
     * Checks retained invocation modifiers after clause binding and before frames.
     *
     * @throws \MySqlMemory\Error\SqlError When a reached invocation is unsupported
     */
    public function options(): void
    {
        foreach (array_column($this->calls, 0) as $call) {
            if ($call instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate) {
                $limit = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Aggregates())->limitation($call->function, $call->distinct);
                if ($limit !== null) {
                    throw \MySqlMemory\Error\Family\StatementError::NotSupportedYet->error($limit->value);
                }
            }
            if (!$call instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction) {
                continue;
            }
            $limit = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Arguments())->limitation($call->nulls, $call->edge);
            if ($limit !== null) {
                throw \MySqlMemory\Error\Family\StatementError::NotSupportedYet->error($limit->value);
            }
        }
    }

    /**
     * Resolves a directly named window before the call's argument preparation.
     *
     * @throws \MySqlMemory\Error\SqlError When the selection has no matching definition
     */
    public function named(Name $name): void
    {
        if (array_filter($this->windows, static fn ($window): bool => strcasecmp($window->name->value, $name->value) === 0) === []) {
            throw QueryError::WindowNotDefined->error($name->value);
        }
    }
}
