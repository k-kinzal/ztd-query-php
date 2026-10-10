<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * The string functions that pick strings by a number: ELT, MAKE_SET and EXPORT_SET.
 *
 * The number is read as an integer, an exact value rounded; the bits of MAKE_SET and EXPORT_SET
 * are its 64 bits, from the lowest. ELT answers the string at a position from 1, NULL outside
 * them; MAKE_SET joins with commas the strings of the bits set, skipping NULL ones; EXPORT_SET
 * writes one of two strings for each bit, joined by a separator (a comma by default), for a
 * number of bits that is 64 when it is below 0 or above 64 (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_elt.
 *
 * @visibility MySqlMemory
 */
final class Lists
{
    /**
     * @param Strings $strings Reads the arguments as text of the result
     */
    public function __construct(public readonly Strings $strings = new Strings())
    {
    }

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('ELT', 2, -1, $this->elt(...)),
            new Routine('MAKE_SET', 2, -1, $this->makeSet(...)),
            new Routine('EXPORT_SET', 3, 5, $this->exportSet(...)),
        ];
    }

    /**
     * ELT(N, str1, str2, ...): the string at position N, or NULL.
     *
     * @param list<Evaluable> $arguments
     */
    public function elt(Frame $frame, array $arguments, Domain $result): ?string
    {
        $position = $this->strings->number($frame, $arguments[0]);
        if ($position === null || $position < 1 || $position >= count($arguments)) {
            return null;
        }

        return $this->strings->text($frame, $arguments[$position], $result);
    }

    /**
     * MAKE_SET(bits, str1, str2, ...): the strings of the bits set, joined by commas.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function makeSet(Frame $frame, array $arguments, Domain $result): ?string
    {
        $bits = Convert::toInteger($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context, true);
        if ($bits === null) {
            return null;
        }
        $parts = [];
        foreach (array_slice($arguments, 1, 64) as $index => $argument) {
            if (($bits >> $index & 1) === 1 && ($text = $this->strings->text($frame, $argument, $result)) !== null) {
                $parts[] = $text;
            }
        }

        return implode(Encoding::convert(',', Charset::known('ascii'), $result->collation->charset), $parts);
    }

    /**
     * EXPORT_SET(bits, on, off[, separator[, number of bits]]): on or off for each bit, joined by the separator.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function exportSet(Frame $frame, array $arguments, Domain $result): ?string
    {
        $bits = Convert::toInteger($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context, true);
        $on = $this->strings->text($frame, $arguments[1], $result);
        $off = $this->strings->text($frame, $arguments[2], $result);
        $separator = isset($arguments[3]) ? $this->strings->text($frame, $arguments[3], $result) : Encoding::convert(',', Charset::known('ascii'), $result->collation->charset);
        $count = isset($arguments[4]) ? Convert::toInteger($arguments[4]->evaluate($frame), $arguments[4]->domain(), $frame->context) : 64;
        if ($bits === null || $on === null || $off === null || $separator === null || $count === null) {
            return null;
        }
        $parts = [];
        for ($index = 0, $last = $count < 0 || $count > 64 ? 64 : $count; $index < $last; $index++) {
            $parts[] = ($bits >> $index & 1) === 1 ? $on : $off;
        }

        return implode($separator, $parts);
    }
}
