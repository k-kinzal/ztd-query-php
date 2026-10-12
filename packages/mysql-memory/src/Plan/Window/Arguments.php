<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Leaf\UserVariableRead;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\CallEffects;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Separates arguments stored once per input row from expressions read by a window.
 * Assignments and other changing expressions are materialized at the input frontier.
 * MySQL 8.0 subsequently reads a bare assignment through the variable itself;
 * 8.4 and 9.1 read its stored value. Verified by assignments inside moving frames.
 *
 * @visibility MySqlMemory
 */
final class Arguments
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers the window operands and the expressions to append to each loaded row.
     *
     * @return array{list<Evaluable>, list<Evaluable>}
     */
    public function split(Analytic $analytic, int $offset, ?Scalar $call): array
    {
        $arguments = $analytic->accumulation->arguments ?? $analytic->arguments;
        $nodes = match (true) {
            $call instanceof Aggregate => $call->arguments,
            $call instanceof JsonObjectAggregate => [$call->key, $call->value],
            $call instanceof WindowFunction => $analytic->kind === WindowFunctionKind::NthValue ? [$call->arguments[0]] : array_values(array_filter($call->arguments, static fn (int $position): bool => $position !== 1, ARRAY_FILTER_USE_KEY)),
            default => [],
        };
        $reads = [];
        $stored = [];
        foreach ($arguments as $index => $argument) {
            if (isset($nodes[$index]) && !$this->stored($nodes[$index]) && !in_array($analytic->kind, [WindowFunctionKind::Lead, WindowFunctionKind::Lag], true)) {
                $reads[] = $argument;
                continue;
            }
            $reads[] = $this->read($argument, $offset + count($stored));
            $stored[] = $argument;
        }

        return [$reads, $stored];
    }

    /**
     * Tells whether evaluating an argument again could change its value or session state.
     */
    public function stored(Scalar $argument): bool
    {
        $effects = new CallEffects();
        foreach ((new Walker())->find($argument, Node::class) as $node) {
            if ($node instanceof Query || $effects->uncacheable($node, $this->planner->settings->release())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads a stored argument, or the live variable used by a bare assignment in 8.0.
     */
    public function read(Evaluable $argument, int $position): Evaluable
    {
        $unwrapped = $argument;
        while ($unwrapped instanceof Retyped) {
            $unwrapped = $unwrapped->evaluable;
        }
        if ($unwrapped instanceof Assignment && $this->planner->settings->release() === GrammarRelease::MySql8044) {
            return new UserVariableRead($unwrapped->name, $argument->domain());
        }

        return new ColumnRead($argument->domain(), $position);
    }
}
