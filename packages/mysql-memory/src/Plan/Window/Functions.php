<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Operand;
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
     * IGNORE NULLS and FROM LAST are refused after names and windows are resolved, before the
     * count is checked (verified on live 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @param bool $direct Whether the query reports the type before window materialization
     * @throws SqlError When an argument is refused or cannot be compiled
     */
    public function compile(Scalar $call, Scope $scope, bool $direct = false): Analytic
    {
        $compiler = $this->planner->compiler;
        foreach ($compiler->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing && $diagnostic->expression === $call) {
                throw StatementError::NotSupportedYet->error($diagnostic->limit->value);
            }
        }
        $domain = $compiler->domain($call);
        if (!$direct) {
            $stored = $call instanceof WindowFunction ? (new \SqlSemantics\Platform\MySql\Rules\Call\WindowResults())->materialized($call->kind, $domain->resolved(), $compiler->settings->release()) : (new \SqlSemantics\Platform\MySql\Rules\Typing\Materialization())->windowed($domain->resolved(), $compiler->settings->release());
            $domain = \MySqlMemory\Typing\Domain::of($stored, $domain->nullable);
        }
        if ($call instanceof Aggregate || $call instanceof JsonObjectAggregate) {
            $accumulation = (new Grouping($this->planner))->accumulation($call, $scope);

            return new Analytic(null, $accumulation, [], 1, $domain->withSource($accumulation->domain->source));
        }
        if (!$call instanceof WindowFunction) {
            throw StatementError::NotSupportedYet->error(Resolution::named($call) . ' as a window function');
        }
        $this->options($call);
        $name = Resolution::named($call);
        $arguments = array_map(static fn (Operand $argument): Evaluable => $compiler->compile($argument, $scope), $call->kind === WindowFunctionKind::Tile || $call->kind === WindowFunctionKind::NthValue ? [] : array_values(array_filter($call->arguments, static fn (int $position): bool => $position !== 1, ARRAY_FILTER_USE_KEY)));

        return match ($call->kind) {
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank, WindowFunctionKind::CumulativeDistribution, WindowFunctionKind::PercentRank,
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue => new Analytic($call->kind, null, $arguments, 1, $domain),
            WindowFunctionKind::Tile => new Analytic($call->kind, null, [], $this->count($call->arguments[0], $name, 1), $domain),
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => new Analytic($call->kind, null, $arguments, isset($call->arguments[1]) ? $this->count($call->arguments[1], $name, 0) : 1, $domain),
            WindowFunctionKind::NthValue => new Analytic($call->kind, null, [$compiler->compile($call->arguments[0], $scope)], $this->nth($call->arguments[1], $name), $domain),
        };
    }

    /**
     * Checks function options after names resolve and before frame validation.
     *
     * @throws SqlError When the written NULL treatment or counting edge is unsupported
     */
    public function options(WindowFunction $call): void
    {
        if ($call->nulls === NullTreatment::Ignore || $call->edge === CountingEdge::Last) {
            throw StatementError::NotSupportedYet->error($call->nulls === NullTreatment::Ignore ? 'IGNORE NULLS' : 'FROM LAST');
        }
    }

    /**
     * Evaluates the count of NTILE or the offset of LEAD and LAG: an integer of at least a minimum.
     *
     * @throws SqlError When the value is not such an integer
     */
    public function count(Operand $argument, string $name, int $minimum): int
    {
        $compiled = $this->planner->compiler->compile($argument, new Scope());
        $value = $compiled->evaluate(new Frame($this->planner->compiler->connection->context));
        $domain = $compiled->domain();
        if ($argument instanceof Parameter && $value !== null && $domain->kind !== Kind::Integer) {
            throw StatementError::WrongArguments->error('EXECUTE');
        }
        if ($value === null || $domain->kind !== Kind::Integer || ($domain->unsigned && (int) \MySqlMemory\Evaluation\ExactDecimal::raw($value) < 0) || (int) \MySqlMemory\Evaluation\ExactDecimal::raw($value) < $minimum) {
            throw StatementError::WrongArguments->error($name);
        }

        return (int) \MySqlMemory\Evaluation\ExactDecimal::raw($value);
    }

    /**
     * Evaluates the row of NTH_VALUE: a positive integer constant for the statement.
     *
     * @throws SqlError When the value is not such an integer
     */
    public function nth(Operand $argument, string $name): int
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
    public function cached(Analytic $analytic, int $offset, ?Scalar $call = null): array
    {
        $accumulation = $analytic->accumulation;
        [$reads, $arguments] = (new Arguments($this->planner))->split($analytic, $offset, $call);
        if ($accumulation === null) {
            return [new Analytic($analytic->kind, null, $reads, $analytic->count, $analytic->domain), $arguments];
        }
        $cached = new Accumulation($accumulation->function, $reads, $accumulation->distinct, $accumulation->domain, $accumulation->order, $accumulation->separator, $accumulation->limit, $accumulation->object, divisionPrecision: $accumulation->divisionPrecision);

        return [new Analytic($analytic->kind, $cached, [], $analytic->count, $analytic->domain), $arguments];
    }
}
