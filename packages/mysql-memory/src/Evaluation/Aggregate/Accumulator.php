<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Aggregate;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

/**
 * The fold of one aggregate over the rows of one group.
 *
 * NULL arguments are skipped. COUNT counts the rows whose arguments are all not NULL; SUM, AVG,
 * MIN and MAX of no value are NULL; the bit aggregates of no value are their identity.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Accumulator
{
    private int $count = 0;

    private int|float|string|null $value = null;

    private float $squares = 0.0;

    private float $mean = 0.0;

    /**
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * @var list<array{string, list<int|float|string|null>}>
     */
    private array $parts = [];

    /**
     * @param Accumulation $accumulation The aggregate folded
     */
    public function __construct(public readonly Accumulation $accumulation)
    {
    }

    /**
     * Folds the arguments evaluated over the row of a frame.
     */
    public function add(Frame $frame): void
    {
        $values = [];
        $key = '';
        foreach ($this->accumulation->arguments as $argument) {
            $value = $argument->evaluate($frame);
            if ($value === null) {
                return;
            }
            $values[] = $value;
            $key .= Order::key($value, $argument->domain()) . "\0";
        }
        if ($this->accumulation->distinct) {
            if (isset($this->seen[$key])) {
                return;
            }
            $this->seen[$key] = true;
        }
        if ($this->accumulation->function === null) {
            $this->concatenate($frame, $values);

            return;
        }
        $this->count++;
        if ($values !== []) {
            $this->fold($frame, $values[0]);
        }
    }

    /**
     * Folds one argument value into the function's state.
     */
    public function fold(Frame $frame, int|float|string $value): void
    {
        $argument = $this->accumulation->arguments[0];
        $domain = $argument->domain();
        $context = $frame->context;
        switch ($this->accumulation->function) {
            case AggregateFunction::Minimum:
            case AggregateFunction::Maximum:
                $order = $this->value === null ? 0 : Order::compare($value, $this->value, $domain);
                if ($this->count === 1 || ($this->accumulation->function === AggregateFunction::Minimum ? $order < 0 : $order > 0)) {
                    $this->value = $value;
                }
                break;
            case AggregateFunction::Sum:
            case AggregateFunction::Average:
                $this->value = $this->accumulation->domain->kind === Kind::Double || ($this->accumulation->function === AggregateFunction::Average && $domain->kind === Kind::Double)
                    ? (float) $this->value + (float) Convert::toDouble($value, $domain, $context)
                    : Decimal::add((string) ($this->value ?? '0'), (string) Convert::toDecimal($value, $domain, $context));
                break;
            case AggregateFunction::BitAnd:
            case AggregateFunction::BitOr:
            case AggregateFunction::BitXor:
                $bits = (int) Convert::toInteger($value, $domain, $context, true);
                $this->value = match ($this->accumulation->function) {
                    AggregateFunction::BitAnd => ($this->count === 1 ? -1 : (int) $this->value) & $bits,
                    AggregateFunction::BitOr => (int) $this->value | $bits,
                    default => (int) $this->value ^ $bits,
                };
                break;
            case AggregateFunction::StandardDeviation:
            case AggregateFunction::Variance:
            case AggregateFunction::SampleStandardDeviation:
            case AggregateFunction::SampleVariance:
                $number = (float) Convert::toDouble($value, $domain, $context);
                $delta = $number - $this->mean;
                $this->mean += $delta / $this->count;
                $this->squares += $delta * ($number - $this->mean);
                break;
            default:
                break;
        }
    }

    /**
     * Folds the values of a GROUP_CONCAT call.
     *
     * @param list<int|float|string> $values
     */
    public function concatenate(Frame $frame, array $values): void
    {
        $text = '';
        foreach ($this->accumulation->arguments as $index => $argument) {
            $text .= (string) Convert::toText($values[$index], $argument->domain());
        }
        $keys = [];
        foreach ($this->accumulation->order as [$key]) {
            $keys[] = $key->evaluate($frame);
        }
        $this->parts[] = [$text, $keys];
        $this->count++;
    }

    /**
     * Answers the result of the fold.
     */
    public function result(Frame $frame): int|float|string|null
    {
        return match ($this->accumulation->function) {
            AggregateFunction::Count => $this->count,
            AggregateFunction::BitAnd => $this->count === 0 ? -1 : $this->value,
            AggregateFunction::BitOr, AggregateFunction::BitXor => $this->count === 0 ? 0 : $this->value,
            AggregateFunction::Average => $this->average(),
            AggregateFunction::Sum => $this->count === 0 ? null : ($this->accumulation->domain->kind === Kind::Decimal ? Decimal::round((string) $this->value, $this->accumulation->domain->decimals) : $this->value),
            AggregateFunction::StandardDeviation, AggregateFunction::SampleStandardDeviation => $this->spread(true),
            AggregateFunction::Variance, AggregateFunction::SampleVariance => $this->spread(false),
            null => $this->concatenation($frame),
            default => $this->count === 0 ? null : $this->value,
        };
    }

    /**
     * Answers the average of the folded values, or null for none.
     */
    public function average(): float|string|null
    {
        if ($this->count === 0) {
            return null;
        }
        if ($this->accumulation->domain->kind === Kind::Double) {
            return (float) $this->value / $this->count;
        }

        return Decimal::divide((string) $this->value, (string) $this->count, $this->accumulation->domain->decimals);
    }

    /**
     * Answers the variance or standard deviation of the folded values, population or sample.
     */
    public function spread(bool $root): ?float
    {
        $sample = $this->accumulation->function === AggregateFunction::SampleStandardDeviation || $this->accumulation->function === AggregateFunction::SampleVariance;
        if ($this->count === 0 || ($sample && $this->count === 1)) {
            return null;
        }
        $variance = $this->squares / ($sample ? $this->count - 1 : $this->count);

        return $root ? sqrt($variance) : $variance;
    }

    /**
     * Answers the GROUP_CONCAT result: the values in order, joined, cut at the length limit.
     */
    public function concatenation(Frame $frame): ?string
    {
        if ($this->parts === []) {
            return null;
        }
        $order = $this->accumulation->order;
        if ($order !== []) {
            usort($this->parts, static function (array $left, array $right) use ($order): int {
                foreach ($order as $index => [$key, $descending]) {
                    $compared = Order::compare($left[1][$index], $right[1][$index], $key->domain());
                    if ($compared !== 0) {
                        return $descending ? -$compared : $compared;
                    }
                }

                return 0;
            });
        }
        $text = implode($this->accumulation->separator, array_column($this->parts, 0));
        if (strlen($text) > $this->accumulation->limit) {
            $text = mb_strcut($text, 0, $this->accumulation->limit, 'UTF-8');
            $frame->context->warning(ErrorCode::CutByGroupConcat, count($this->parts));
        }

        return $text;
    }
}
