<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles the window function calls of a query block, checking the arguments the server reads when it prepares the statement.
 *
 * The count of NTILE must be a positive integer and the offset of LEAD and LAG a non-negative
 * one, each an integer literal, parameter or variable holding an integer, at most the largest
 * BIGINT; the row of NTH_VALUE must be a positive integer constant for the statement. Any other
 * value, NULL included, is refused with ER_WRONG_ARGUMENTS, even when the query reads no row,
 * after every window is checked; a parameter marker bound to a value that is not an integer is
 * refused as an incorrect argument of EXECUTE (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html.
 *
 * @visibility MySqlMemory
 */
final class Functions
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Compiles one call computed over a window; its arguments are read from the rows of the block.
     *
     * @throws SqlError When an argument is refused or cannot be compiled
     */
    public function compile(Scalar $call, Scope $scope): Analytic
    {
        $compiler = $this->planner->compiler;
        if ($call instanceof Aggregate || $call instanceof JsonObjectAggregate) {
            return new Analytic(null, (new Grouping($this->planner))->accumulation($call, $scope), [], 1, $compiler->domain($call));
        }
        if (!$call instanceof WindowFunction) {
            throw StatementError::NotSupportedYet->error(Resolution::named($call) . ' as a window function');
        }
        $domain = $compiler->domain($call);
        $name = Resolution::named($call);
        $arguments = array_map(static fn (Scalar $argument): Evaluable => $compiler->compile($argument, $scope), $call->kind === WindowFunctionKind::Tile || $call->kind === WindowFunctionKind::NthValue ? [] : array_values(array_filter($call->arguments, static fn (int $position): bool => $position !== 1, ARRAY_FILTER_USE_KEY)));

        return match ($call->kind) {
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank, WindowFunctionKind::CumulativeDistribution, WindowFunctionKind::PercentRank,
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue => new Analytic($call->kind, null, $arguments, 1, $domain),
            WindowFunctionKind::Tile => new Analytic($call->kind, null, [], $this->count($call->arguments[0], $name, 1), $domain),
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => new Analytic($call->kind, null, $arguments, isset($call->arguments[1]) ? $this->count($call->arguments[1], $name, 0) : 1, $domain),
            WindowFunctionKind::NthValue => new Analytic($call->kind, null, [$compiler->compile($call->arguments[0], $scope)], $this->nth($call->arguments[1], $name), $domain),
        };
    }

    /**
     * Evaluates the count of NTILE or the offset of LEAD and LAG: an integer of at least a minimum.
     *
     * @throws SqlError When the value is not such an integer
     */
    public function count(Scalar $argument, string $name, int $minimum): int
    {
        $compiled = $this->planner->compiler->compile($argument, new Scope());
        $value = $compiled->evaluate(new Frame($this->planner->compiler->connection->context));
        $domain = $compiled->domain();
        if ($argument instanceof Parameter && $value !== null && $domain->kind !== Kind::Integer) {
            throw StatementError::WrongArguments->error('EXECUTE');
        }
        if ($value === null || $domain->kind !== Kind::Integer || ($domain->unsigned && (int) $value < 0) || (int) $value < $minimum) {
            throw StatementError::WrongArguments->error($name);
        }

        return (int) $value;
    }

    /**
     * Evaluates the row of NTH_VALUE: a positive integer constant for the statement.
     *
     * @throws SqlError When the value is not such an integer
     */
    public function nth(Scalar $argument, string $name): int
    {
        if (!$this->planner->compiler->constancy($argument)->constant()) {
            throw StatementError::WrongArguments->error($name);
        }

        return $this->count($argument, $name, 1);
    }

    /**
     * Answers a call that reads its arguments from the row after a position, where the window holds them once evaluated, and the arguments it reads there.
     *
     * @return array{Analytic, list<Evaluable>}
     */
    public function cached(Analytic $analytic, int $offset): array
    {
        $accumulation = $analytic->accumulation;
        $arguments = $accumulation === null ? $analytic->arguments : $accumulation->arguments;
        $reads = array_map(static fn (Evaluable $argument, int $position): Evaluable => new ColumnRead($argument->domain(), $offset + $position), $arguments, array_keys($arguments));
        if ($accumulation === null) {
            return [new Analytic($analytic->kind, null, $reads, $analytic->count, $analytic->domain), $arguments];
        }
        $cached = new Accumulation($accumulation->function, $reads, $accumulation->distinct, $accumulation->domain, $accumulation->order, $accumulation->separator, $accumulation->limit, $accumulation->object);

        return [new Analytic($analytic->kind, $cached, [], $analytic->count, $analytic->domain), $arguments];
    }
}
