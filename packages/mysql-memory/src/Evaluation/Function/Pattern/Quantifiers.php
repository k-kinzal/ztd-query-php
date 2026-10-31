<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Reads the quantifier after a term as ICU does: * + ? {n} {n,} {n,m}, each greedy, lazy (?) or possessive (+).
 *
 * A quantifier after a term that cannot be repeated, or after another quantifier, is a syntax
 * error; an interval without a number or a closing brace is ER_REGEXP_BAD_INTERVAL, a maximum
 * below the minimum ER_REGEXP_MAX_LT_MIN, and a number above 16777215 ER_REGEXP_NUMBER_TOO_BIG.
 * Source: https://unicode-org.github.io/icu/userguide/strings/regexp.html; the errors were verified on a live 8.4 server.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Quantifiers
{
    /**
     * The quantifier characters.
     */
    public const QUANTIFIERS = ['*', '+', '?', '{'];

    /**
     * The largest number an interval or a back reference may write.
     */
    public const LARGEST = 16777215;

    /**
     * @param Translator $translator The translator whose pattern is read
     */
    public function __construct(public readonly Translator $translator)
    {
    }

    /**
     * Reads the quantifier after a term, if any, and answers the term repeated.
     *
     * @throws SqlError When the quantifier is not valid
     */
    public function quantified(Fragment $atom): Fragment
    {
        $scanner = $this->translator->scanner;
        $this->translator->skip();
        if (!in_array($scanner->peek(), self::QUANTIFIERS, true)) {
            return $atom;
        }
        $quantifier = $scanner->next();
        if (!$atom->quantifiable) {
            throw $scanner->syntax();
        }
        [$minimum, $maximum] = match ($quantifier) {
            '*' => [0, null],
            '+' => [1, null],
            '?' => [0, 1],
            default => $this->interval(),
        };
        $this->translator->skip();
        $modifier = '';
        if ($scanner->peek() === '?' || $scanner->peek() === '+') {
            $modifier = (string) $scanner->next();
            $this->translator->skip();
        }
        if (in_array($scanner->peek(), self::QUANTIFIERS, true)) {
            $scanner->next();
            throw $scanner->syntax();
        }

        return $this->repeat($atom, $minimum, $maximum, $modifier);
    }

    /**
     * Reads the bounds of an interval after its brace.
     *
     * @return array{int, int|null}
     *
     * @throws SqlError When the interval is not valid
     */
    public function interval(): array
    {
        $minimum = $this->number();
        if ($minimum === null) {
            throw DataError::RegexpBadInterval->error();
        }
        $maximum = $minimum;
        $this->translator->skip();
        $character = $this->translator->scanner->next();
        if ($character === ',') {
            $maximum = $this->number();
            $this->translator->skip();
            $character = $this->translator->scanner->next();
        }
        if ($character !== '}') {
            throw DataError::RegexpBadInterval->error();
        }
        if ($maximum !== null && $maximum < $minimum) {
            throw DataError::RegexpMaximumBelowMinimum->error();
        }

        return [$minimum, $maximum];
    }

    /**
     * Reads a decimal number, or answers null when no digit follows.
     *
     * @throws SqlError When the number exceeds 16777215
     */
    public function number(): ?int
    {
        $this->translator->skip();
        $number = null;
        while (ctype_digit($this->translator->scanner->peek() ?? '')) {
            $number = ($number ?? 0) * 10 + (int) $this->translator->scanner->next();
            if ($number > self::LARGEST) {
                throw DataError::RegexpNumberTooBig->error();
            }
        }

        return $number;
    }

    /**
     * Answers a term repeated between two counts; PCRE compiles counts up to some thousands.
     */
    public function repeat(Fragment $atom, int $minimum, ?int $maximum, string $modifier): Fragment
    {
        $unit = '(?:' . $atom->source . ')';
        $quantifier = match (true) {
            $minimum === 0 && $maximum === null => '*',
            $minimum === 1 && $maximum === null => '+',
            $minimum === 0 && $maximum === 1 => '?',
            $minimum === $maximum => '{' . $minimum . '}',
            $maximum === null => '{' . $minimum . ',}',
            default => '{' . $minimum . ',' . $maximum . '}',
        };
        $most = match (true) {
            $atom->maximum === 0 => 0,
            $maximum === null || $atom->maximum === null => null,
            default => $atom->maximum * $maximum,
        };

        return new Fragment($unit . $quantifier . $modifier, $atom->minimum * $minimum, $most);
    }
}
