<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Reads a set in brackets as ICU does: characters, escapes and ranges, nested sets, [:property:]
 * and [:^property:], \p{..} and \P{..}, a leading ^ that complements the set, && (intersection)
 * and -- (difference).
 *
 * A ] right after the opening bracket stands for itself; white space is skipped in the x mode.
 * An operator without a set on either side is a syntax error, a range whose end precedes its
 * start ER_REGEXP_INVALID_RANGE, and a set left open ER_REGEXP_MISSING_CLOSE_BRACKET.
 * Source: https://unicode-org.github.io/icu/userguide/strings/regexp.html; the errors were verified on a live 8.4 server.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Brackets
{
    /**
     * The escape letters that name a class, which cannot end a range.
     */
    public const CLASSES = ['d', 'D', 's', 'S', 'w', 'W', 'h', 'H', 'v', 'V'];

    /**
     * @param Translator $translator The translator whose pattern is read
     */
    public function __construct(public readonly Translator $translator)
    {
    }

    /**
     * Reads a set in brackets after its opening bracket.
     *
     * @throws SqlError When the set is not valid
     */
    public function set(): Members
    {
        $scanner = $this->translator->scanner;
        $negated = $scanner->peek() === '^';
        if ($negated) {
            $scanner->next();
        }
        $result = null;
        $operator = null;
        $union = [];
        $last = null;
        $first = true;
        while (true) {
            $this->blank();
            $character = $scanner->peek();
            if ($character === null) {
                throw DataError::RegexpMissingCloseBracket->error();
            }
            if ($character === ']' && !$first) {
                $scanner->next();
                if ($operator !== null && $union === []) {
                    throw $scanner->syntax();
                }
                break;
            }
            $pair = $character . ($scanner->peek(1) ?? '');
            if ($pair === '&&' || $pair === '--') {
                $scanner->next();
                $scanner->next();
                if ($union === []) {
                    throw $scanner->syntax();
                }
                $result = $this->combine($result, $operator, Members::union($union));
                $operator = $pair;
                $union = [];
                $last = null;
            } elseif ($character === '-' && $last !== null && !in_array($scanner->peek(1), [']', '[', null], true)) {
                array_pop($union);
                $union[] = $this->range($last);
                $last = null;
            } else {
                [$members, $last] = $this->member();
                $union[] = $members;
            }
            $first = false;
        }
        $members = $this->combine($result, $operator, Members::union($union));

        return $negated ? $members->complement() : $members;
    }

    /**
     * Skips white space in a set in the x mode.
     */
    public function blank(): void
    {
        while ($this->translator->mode->comments && $this->translator->space($this->translator->scanner->peek() ?? 'x')) {
            $this->translator->scanner->next();
        }
    }

    /**
     * Reads the end of a range from its hyphen, and answers the range from its start.
     *
     * @throws SqlError When the end is a class or precedes the start
     */
    public function range(string $last): Members
    {
        $scanner = $this->translator->scanner;
        $scanner->next();
        $upper = $scanner->next();
        if ($upper === '\\') {
            $letter = (string) $scanner->next();
            $upper = $this->translator->escapes->code($letter) ?? (in_array($letter, [...self::CLASSES, 'p', 'P'], true) ? throw $scanner->syntax() : $letter);
        }
        if (mb_ord((string) $upper, 'UTF-8') < mb_ord($last, 'UTF-8')) {
            throw DataError::RegexpInvalidRange->error();
        }

        return Members::range($last, (string) $upper);
    }

    /**
     * Reads one member of a set: a character, an escape, a nested set or a [:property:].
     *
     * @return array{Members, string|null} The members, and the character when it is a single one
     *
     * @throws SqlError When the member is not valid
     */
    public function member(): array
    {
        $scanner = $this->translator->scanner;
        $character = (string) $scanner->next();
        if ($character === '[' && $scanner->peek() === ':') {
            $scanner->next();
            $name = '';
            while (!($scanner->peek() === ':' && $scanner->peek(1) === ']')) {
                $next = $scanner->next();
                if ($next === null) {
                    throw DataError::RegexpMissingCloseBracket->error();
                }
                $name .= $next;
            }
            $scanner->next();
            $scanner->next();
            $negated = str_starts_with($name, '^');
            $members = $this->translator->properties->members($negated ? substr($name, 1) : $name) ?? throw DataError::RegexpError->error();
            $members = $this->translator->mode->caseless ? $members->closed() : $members;

            return [$negated ? $members->complement() : $members, null];
        }
        if ($character === '[') {
            return [$this->set(), null];
        }
        if ($character !== '\\') {
            return [Members::character($character), $character];
        }
        $letter = $scanner->next();
        if ($letter === null) {
            throw DataError::RegexpBadEscapeSequence->error();
        }
        if (in_array($letter, self::CLASSES, true)) {
            return [$this->translator->properties->escape($letter, $this->translator->mode->caseless), null];
        }
        if ($letter === 'p' || $letter === 'P') {
            return [$this->translator->escapes->property($letter === 'P', true), null];
        }
        $code = $this->translator->escapes->code($letter) ?? $letter;

        return [Members::character($code), $code];
    }

    /**
     * Answers the set an operator makes of the set so far and the next one.
     */
    public function combine(?Members $left, ?string $operator, Members $right): Members
    {
        return match (true) {
            $left === null => $right,
            $operator === '&&' => $left->intersect($right),
            default => $left->minus($right),
        };
    }
}
