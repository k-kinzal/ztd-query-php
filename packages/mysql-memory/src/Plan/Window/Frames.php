<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\DateShift;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\Bound;
use MySqlMemory\Evaluation\Window\Shift;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Checks the frame of each window of a query block and compiles it.
 *
 * The server refuses an INTERVAL offset of a ROWS frame first (ER_WINDOW_ROWS_INTERVAL_USE).
 * A RANGE frame with an offset needs exactly one ORDER BY expression, numeric or temporal
 * (ER_WINDOW_RANGE_FRAME_ORDER_TYPE); its offsets are INTERVALs for a temporal one
 * (ER_WINDOW_RANGE_FRAME_TEMPORAL_TYPE) and numbers for a numeric one
 * (ER_WINDOW_RANGE_FRAME_NUMERIC_TYPE), constant for the statement
 * (ER_WINDOW_RANGE_BOUND_NOT_CONSTANT). Last, an offset of a ROWS frame that is not a
 * non-negative integer, one of a RANGE frame that is NULL or negative, and a frame that starts
 * after the kind of boundary it ends at are refused (ER_WINDOW_FRAME_ILLEGAL), but a parameter
 * marker of a ROWS frame bound to a value that is not an integer is an incorrect argument of
 * EXECUTE; a frame whose
 * offsets leave it empty is not (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility MySqlMemory
 */
final class Frames
{
    /**
     * The order of the kinds of boundary: a frame cannot start at a kind after the one it ends at.
     */
    public const POSITIONS = ['UNBOUNDED PRECEDING' => 0, 'PRECEDING' => 1, 'CURRENT ROW' => 2, 'FOLLOWING' => 3, 'UNBOUNDED FOLLOWING' => 4];

    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Checks the frame of a window.
     *
     * @throws SqlError When the frame is refused
     */
    public function check(Specification $window, Scope $scope): void
    {
        $frame = $window->frame();
        if ($frame === null) {
            return;
        }
        $bounds = array_values(array_filter([$frame->start, $frame->end], static fn (?FrameBound $bound): bool => $bound !== null && $bound->offset !== null));
        if ($frame->unit === FrameUnit::Rows && array_filter($bounds, static fn (FrameBound $bound): bool => $bound->unit !== null) !== []) {
            throw QueryError::WindowRowsInterval->error($window->name);
        }
        if ($frame->unit === FrameUnit::Range && $bounds !== []) {
            $this->range($window, $bounds, $scope);
        }
        foreach ($bounds as $bound) {
            $this->offset($window, $frame->unit, $bound, $scope);
        }
        if (self::POSITIONS[$frame->start->kind->value] > self::POSITIONS[($frame->end->kind ?? FrameBoundKind::CurrentRow)->value]) {
            throw QueryError::WindowFrameIllegal->error($window->name);
        }
    }

    /**
     * Checks the ORDER BY of a window with a RANGE frame with offsets, and that the offsets suit it and are constant.
     *
     * @param list<FrameBound> $bounds The boundaries with an offset
     * @throws SqlError When the ordering or an offset is refused
     */
    public function range(Specification $window, array $bounds, Scope $scope): void
    {
        $compiler = $this->planner->compiler;
        $kind = count($window->order) === 1 ? $compiler->compile($window->order[0]->expression, $scope)->domain()->kind : Kind::Null;
        $temporal = $kind->temporal();
        if (!$temporal && (!$kind->numeric() || $kind === Kind::Bit)) {
            throw QueryError::WindowRangeOrderType->error($window->name);
        }
        foreach ($bounds as $bound) {
            if ($temporal && $bound->unit === null) {
                throw QueryError::WindowRangeTemporalOffset->error($window->name);
            }
            if (!$temporal && $bound->unit !== null) {
                throw QueryError::WindowRangeNumericOffset->error($window->name);
            }
        }
        foreach ($bounds as $bound) {
            if ($bound->offset !== null && !$compiler->constancy($bound->offset)->constant()) {
                throw QueryError::WindowFrameBoundNotConstant->error($window->name);
            }
        }
    }

    /**
     * Checks the value of an offset: a non-negative integer for a ROWS frame, not NULL nor negative for a RANGE frame.
     *
     * @throws SqlError When the value is refused
     */
    public function offset(Specification $window, FrameUnit $unit, FrameBound $bound, Scope $scope): void
    {
        if ($unit === FrameUnit::Rows && $bound->offset instanceof NumberLiteral) {
            if (!ctype_digit($bound->offset->text) || Decimal::compare($bound->offset->text, '18446744073709551615') > 0) {
                throw QueryError::WindowFrameIllegal->error($window->name);
            }

            return;
        }
        [$value, $domain] = $this->value($bound, $scope);
        if ($unit === FrameUnit::Rows && $bound->offset instanceof Parameter && $value !== null && $domain->kind !== Kind::Integer) {
            throw StatementError::WrongArguments->error('EXECUTE');
        }
        $negative = match (true) {
            $value === null => true,
            $domain->kind === Kind::String => str_starts_with(ltrim((string) $value), '-'),
            default => Decimal::compare((string) Convert::toDecimal($value, $domain, $this->quiet()), '0') < 0,
        };
        if ($negative || ($unit === FrameUnit::Rows && $domain->kind !== Kind::Integer)) {
            throw QueryError::WindowFrameIllegal->error($window->name);
        }
    }

    /**
     * Evaluates an offset when the statement is planned, and answers it with its domain.
     *
     * @return array{int|float|string|null, Domain}
     * @throws SqlError When evaluating the offset is an error
     */
    public function value(FrameBound $bound, Scope $scope): array
    {
        if ($bound->offset === null) {
            return [null, Domain::null()];
        }
        $offset = $this->planner->compiler->compile($bound->offset, new Scope($scope->outer));

        return [$offset->evaluate(new Frame($this->quiet())), $offset->domain()];
    }

    /**
     * Answers a context for evaluating offsets apart from the statement, whose warnings are not kept.
     */
    public function quiet(): Context
    {
        $context = $this->planner->compiler->connection->context;

        return new Context($context->modes, new Diagnostics(), $context->variables, $context->started);
    }

    /**
     * Compiles the frame of a window over its compiled ORDER BY expressions.
     *
     * @param list<array{Evaluable, bool}> $order
     * @throws SqlError When an offset cannot be compiled
     */
    public function compile(Specification $window, array $order, Scope $scope): WindowFrame
    {
        $frame = $window->frame();
        if ($frame === null) {
            return WindowFrame::default($order !== []);
        }
        $end = $frame->end === null ? new Bound(FrameBoundKind::CurrentRow) : $this->bound($frame->unit, $frame->end, $order, $scope);

        return new WindowFrame($frame->unit, $this->bound($frame->unit, $frame->start, $order, $scope), $end);
    }

    /**
     * Compiles one boundary: the number of rows of an offset of a ROWS frame, or where an offset of a RANGE frame moves the ordering value.
     *
     * @param list<array{Evaluable, bool}> $order
     * @throws SqlError When the offset cannot be compiled
     */
    public function bound(FrameUnit $unit, FrameBound $bound, array $order, Scope $scope): Bound
    {
        if ($bound->offset === null || ($unit === FrameUnit::Range && $order === [])) {
            return new Bound($bound->kind);
        }
        [$value, $domain] = $this->value($bound, $scope);
        if ($unit !== FrameUnit::Range) {
            $count = (string) Convert::toDecimal($value, $domain, $this->quiet());
            $rows = Decimal::compare($count, (string) PHP_INT_MAX) > 0 ? PHP_INT_MAX : (int) $count;

            return new Bound($bound->kind, $rows);
        }
        [$key, $descending] = $order[0];
        $subtract = ($bound->kind === FrameBoundKind::Preceding) !== $descending;
        if ($bound->unit !== null) {
            $moved = $key->domain()->kind === Kind::Time ? $key->domain() : new Domain(Kind::DateTime, Field::DateTime, 26, 6);
            $quantity = $this->planner->compiler->compile($bound->offset, new Scope($scope->outer));

            return new Bound($bound->kind, 0, new DateShift($key, $quantity, $bound->unit, $subtract, $moved->withNullable(true)));
        }
        $double = $key->domain()->kind === Kind::Double || $domain->kind === Kind::Double;
        $amount = $double ? (string) (float) $value : (string) Convert::toDecimal($value, $domain, $this->quiet());

        return new Bound($bound->kind, 0, new Shift($key, $amount, $subtract, $double ? Domain::double() : Domain::decimal(65, 30)));
    }
}
