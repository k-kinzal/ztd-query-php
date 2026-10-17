<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

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
     * @var list<array{WindowFunction|Aggregate|GroupConcat|JsonObjectAggregate, list<Evaluable>}> Calls and their prepared arguments
     */
    public array $calls = [];

    /**
     * @param Planner $planner The statement's compiler and bound facts
     * @param int $offset The first window output slot
     */
    public function __construct(public readonly Planner $planner, public readonly int $offset)
    {
    }

    /**
     * Resolves a reached call's input without moving its option checks before a later clause.
     */
    public function compile(Scalar $call, Scope $scope): Evaluable
    {
        \SqlSemantics\Diagnostic\Check::invariant($call instanceof WindowFunction || $call instanceof Aggregate || $call instanceof GroupConcat || $call instanceof JsonObjectAggregate, 'A collected window retains its semantic function call.');
        $input = clone $scope;
        $input->windowing = null;
        $arguments = $call instanceof JsonObjectAggregate ? [$call->key, $call->value] : $call->arguments;
        $compiler = $this->planner->compiler;
        $prepared = array_map(static fn (Operand $argument): Evaluable => $compiler->compile($argument, $input), $arguments);
        if ($call->over instanceof Name) {
            throw QueryError::WindowNotDefined->error($call->over->value);
        }
        $read = new ColumnRead($compiler->domain($call), $this->offset + count($this->calls));
        $this->calls[] = [$call, $prepared];
        $scope->bind($call, $read);
        return $read;
    }
}
