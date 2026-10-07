<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Coerce;
use MySqlMemory\Evaluation\Operator\Comparator;
use MySqlMemory\Result\FieldType;
use MySqlMemory\Typing\Aggregation;
use MySqlMemory\Typing\Domain;

/**
 * The flow control functions IF, IFNULL, NULLIF and COALESCE, and ISNULL.
 *
 * The result of IF, IFNULL and COALESCE is in the domain their candidate values aggregate to;
 * NULLIF answers its first argument, or NULL when both are equal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Control
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('IF', 3, 3, fn (array $d, Signature $s): Domain => (new Aggregation($s->settings->connectionCollation))->of([$d[1], $d[2]], 'if'), $this->if(...)),
            new Routine('IFNULL', 2, 2, fn (array $d, Signature $s): Domain => (new Aggregation($s->settings->connectionCollation))->of($d, 'ifnull')->withNullable($d[0]->nullable && $d[1]->nullable), $this->coalesce(...)),
            new Routine('COALESCE', 1, -1, fn (array $d, Signature $s): Domain => (new Aggregation($s->settings->connectionCollation))->of($d, 'coalesce')->withNullable(count(array_filter($d, static fn (Domain $x): bool => !$x->nullable)) === 0), $this->coalesce(...)),
            new Routine('NULLIF', 2, 2, fn (array $d, Signature $s): Domain => $d[0]->withNullable(true), $this->nullif(...)),
            new Routine('ISNULL', 1, 1, fn (array $d, Signature $s): Domain => Domain::integer(FieldType::LongLong, 1)->withNullable(false), fn (Frame $f, array $a, Domain $r): int => $a[0]->evaluate($f) === null ? 1 : 0),
        ];
    }

    /**
     * IF(condition, then, else).
     *
     * @param list<Evaluable> $arguments
     */
    public function if(Frame $frame, array $arguments, Domain $result): int|float|string|null
    {
        $chosen = Convert::toBool($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context) === true ? $arguments[1] : $arguments[2];

        return Coerce::to($chosen->evaluate($frame), $chosen->domain(), $result, $frame->context);
    }

    /**
     * IFNULL and COALESCE: the first argument that is not NULL.
     *
     * @param list<Evaluable> $arguments
     */
    public function coalesce(Frame $frame, array $arguments, Domain $result): int|float|string|null
    {
        foreach ($arguments as $argument) {
            $value = $argument->evaluate($frame);
            if ($value !== null) {
                return Coerce::to($value, $argument->domain(), $result, $frame->context);
            }
        }

        return null;
    }

    /**
     * NULLIF(a, b): NULL when a equals b, else a.
     *
     * @param list<Evaluable> $arguments
     */
    public function nullif(Frame $frame, array $arguments, Domain $result): int|float|string|null
    {
        $value = $arguments[0]->evaluate($frame);
        $other = $arguments[1]->evaluate($frame);
        $comparator = Comparator::of($arguments[0]->domain(), $arguments[1]->domain(), 'nullif', $result->collation);

        return $comparator->compare($value, $other, $frame->context) === 0 ? null : $value;
    }
}
