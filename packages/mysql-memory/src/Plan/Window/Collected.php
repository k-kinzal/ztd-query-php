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
     * @var list<array{WindowFunction|Aggregate|GroupConcat|JsonObjectAggregate|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction, list<Evaluable>}> Calls and their prepared arguments
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
        foreach ($specifications as [$name, $specification]) {
            \SqlSemantics\Diagnostic\Check::invariant($specification instanceof \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec, 'A MySQL window retains its complete specification.');
            foreach (['window partition by' => $specification->partition, 'window order by' => $specification->order] as $clause => $items) {
                foreach ($items as $item) {
                    (new Resolution())->item($name, $item);
                    $this->planner->compiler->compile($item->expression, $input->inClause($clause));
                }
            }
        }
        (new Resolution())->windows($this->windows, $references);
        foreach ($calls as $call) {
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
