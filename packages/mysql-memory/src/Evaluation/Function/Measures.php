<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * The string functions that answer numbers: LENGTH, CHAR_LENGTH, ASCII, LOCATE, INSTR, STRCMP, FIELD and FIND_IN_SET.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Measures
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        $strings = new Strings();

        return [
            new Routine('LENGTH', 1, 1, fn (Frame $f, array $a): ?int => $this->bytes($f, $a[0])),
            new Routine('OCTET_LENGTH', 1, 1, fn (Frame $f, array $a): ?int => $this->bytes($f, $a[0])),
            new Routine('BIT_LENGTH', 1, 1, fn (Frame $f, array $a): ?int => ($b = $this->bytes($f, $a[0])) === null ? null : $b * 8),
            new Routine('CHAR_LENGTH', 1, 1, fn (Frame $f, array $a): ?int => $this->characters($f, $a[0], $strings)),
            new Routine('CHARACTER_LENGTH', 1, 1, fn (Frame $f, array $a): ?int => $this->characters($f, $a[0], $strings)),
            new Routine('ASCII', 1, 1, fn (Frame $f, array $a): ?int => ($t = Convert::toText($a[0]->evaluate($f), $a[0]->domain())) === null ? null : ($t === '' ? 0 : ord($t[0]))),
            new Routine('LOCATE', 2, 3, fn (Frame $f, array $a, Domain $r): ?int => $this->locate($f, $a[0], $a[1], $a[2] ?? null)),
            new Routine('INSTR', 2, 2, fn (Frame $f, array $a, Domain $r): ?int => $this->locate($f, $a[1], $a[0], null)),
            new Routine('STRCMP', 2, 2, $this->strcmp(...)),
            new Routine('FIELD', 2, -1, $this->field(...)),
            new Routine('FIND_IN_SET', 2, 2, $this->findInSet(...)),
        ];
    }

    /**
     * Counts the bytes of the text of an argument.
     */
    public function bytes(Frame $frame, Evaluable $argument): ?int
    {
        $text = Convert::toText($argument->evaluate($frame), $argument->domain());

        return $text === null ? null : strlen($text);
    }

    /**
     * Counts the characters of the text of an argument.
     */
    public function characters(Frame $frame, Evaluable $argument, Strings $strings): ?int
    {
        $text = Convert::toText($argument->evaluate($frame), $argument->domain());

        return $text === null ? null : count($strings->characters($text, $argument->domain()));
    }

    /**
     * LOCATE(substring, text[, start]): the position of the first occurrence at or after a start, or 0.
     */
    public function locate(Frame $frame, Evaluable $needle, Evaluable $haystack, ?Evaluable $start): ?int
    {
        $search = Convert::toText($needle->evaluate($frame), $needle->domain());
        $text = Convert::toText($haystack->evaluate($frame), $haystack->domain());
        $from = $start === null ? 1 : Convert::toInteger($start->evaluate($frame), $start->domain(), $frame->context);
        if ($search === null || $text === null || $from === null) {
            return null;
        }
        [$collation] = Collations::aggregate([$needle->domain(), $haystack->domain()], 'locate', $haystack->domain()->collation, true);
        $strings = new Strings();
        $characters = $strings->characters($text, $haystack->domain());
        $pattern = $strings->characters($search, $needle->domain());
        if ($from < 1 || $from > count($characters) + 1) {
            return 0;
        }
        $length = count($pattern);
        for ($i = $from - 1, $last = count($characters) - $length; $i <= $last; $i++) {
            if (Ordering::of($collation)->compare(implode('', array_slice($characters, $i, $length)), $search) === 0) {
                return $i + 1;
            }
        }

        return 0;
    }

    /**
     * STRCMP: -1, 0 or 1 by the collation of the arguments.
     *
     * @param list<Evaluable> $arguments
     */
    public function strcmp(Frame $frame, array $arguments, Domain $result): ?int
    {
        $comparator = Comparator::of($arguments[0]->domain(), $arguments[1]->domain(), 'strcmp', $arguments[0]->domain()->collation);
        $order = $comparator->compare(Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain()), Convert::toText($arguments[1]->evaluate($frame), $arguments[1]->domain()), $frame->context);

        return $order === null ? null : $order <=> 0;
    }

    /**
     * FIELD(value, candidate...): the position of the first candidate equal to the value, or 0.
     *
     * @param list<Evaluable> $arguments
     */
    public function field(Frame $frame, array $arguments, Domain $result): int
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return 0;
        }
        foreach (array_slice($arguments, 1) as $index => $candidate) {
            $comparator = Comparator::of($arguments[0]->domain(), $candidate->domain(), 'field', $arguments[0]->domain()->collation);
            if ($comparator->compare($value, $candidate->evaluate($frame), $frame->context) === 0) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * FIND_IN_SET(value, list): the position of the value in a comma-separated list, or 0.
     *
     * @param list<Evaluable> $arguments
     */
    public function findInSet(Frame $frame, array $arguments, Domain $result): ?int
    {
        $value = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());
        $list = Convert::toText($arguments[1]->evaluate($frame), $arguments[1]->domain());
        if ($value === null || $list === null) {
            return null;
        }
        if ($list === '' || str_contains($value, ',')) {
            return 0;
        }
        [$collation] = Collations::aggregate([$arguments[0]->domain(), $arguments[1]->domain()], 'find_in_set', $arguments[1]->domain()->collation, true);
        foreach (explode(',', $list) as $index => $member) {
            if (Ordering::of($collation)->compare($member, $value) === 0) {
                return $index + 1;
            }
        }

        return 0;
    }
}
