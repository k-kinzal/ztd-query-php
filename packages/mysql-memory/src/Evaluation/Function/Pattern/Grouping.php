<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Reads a group as ICU does: (..), (?:..), (?>..), (?<name>..), the lookahead (?=..) (?!..) and
 * lookbehind (?<=..) (?<!..) assertions, (?#..) comments and the flags (?dimsuwx-dimsuwx) and
 * (?dimsuwx-dimsuwx:..).
 *
 * Flags set in a group hold up to its end; flags that end with a parenthesis hold for the rest of
 * the enclosing group. A name begins with a letter and holds letters and digits, and no two
 * groups share one. A lookbehind must have a bounded length (ER_REGEXP_LOOK_BEHIND_LIMIT).
 * Source: https://unicode-org.github.io/icu/userguide/strings/regexp.html; the errors were verified on a live 8.4 server.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Grouping
{
    /**
     * The flags a pattern can set inline.
     */
    public const FLAGS = ['d', 'i', 'm', 's', 'u', 'w', 'x'];

    /**
     * @param Translator $translator The translator whose pattern is read
     */
    public function __construct(public readonly Translator $translator)
    {
    }

    /**
     * Reads a group after its parenthesis: a capture, a named capture, a group without capture, an atomic group, an assertion, a comment or flags.
     *
     * @return list<Fragment>
     *
     * @throws SqlError When the group is not valid
     */
    public function group(): array
    {
        $scanner = $this->translator->scanner;
        $saved = $this->translator->mode;
        if ($scanner->peek() === '*') {
            $scanner->next();
            throw $scanner->syntax();
        }
        if ($scanner->peek() !== '?') {
            $this->translator->groups++;

            return $this->close('(', $saved);
        }
        $scanner->next();
        $kind = $scanner->next();
        if ($kind === '<' && in_array($scanner->peek(), ['=', '!'], true)) {
            $kind .= $scanner->next();
        }

        return match ($kind) {
            ':', '>' => $this->close('(?' . $kind, $saved),
            '=', '!', '<=', '<!' => $this->close('(?' . $kind, $saved, true, str_starts_with($kind, '<')),
            '<' => $this->capture($saved),
            '#' => $this->comment(),
            null => throw $scanner->syntax(),
            default => $this->flagged($kind, $saved),
        };
    }

    /**
     * Reads the body of a group up to its closing parenthesis, restores the mode it began in, and answers the group.
     *
     * @param string $open The opening the group is written with
     * @param Mode $saved The mode before the group
     * @param bool $assertion Whether the group is an assertion, which matches no characters
     * @param bool $behind Whether the group is a lookbehind, whose length must be bounded
     * @return list<Fragment>
     *
     * @throws SqlError When the body is not valid or the group is not closed
     */
    public function close(string $open, Mode $saved, bool $assertion = false, bool $behind = false): array
    {
        $inner = $this->translator->alternation();
        if ($this->translator->scanner->peek() !== ')') {
            throw DataError::RegexpMismatchedParenthesis->error();
        }
        $this->translator->scanner->next();
        $this->translator->mode = $saved;
        if ($behind && $inner->maximum === null) {
            throw DataError::RegexpLookBehindLimit->error();
        }
        $source = $open . $inner->source . ')';

        return [$assertion ? Fragment::empty($source) : new Fragment($source, $inner->minimum, $inner->maximum)];
    }

    /**
     * Reads a named capture group after its (?<.
     *
     * @return list<Fragment>
     *
     * @throws SqlError When the name or the body is not valid
     */
    public function capture(Mode $saved): array
    {
        $this->name();

        return $this->close('(', $saved);
    }

    /**
     * Reads a group of flags from their first letter: a group they scope, or none when they hold for the rest of the enclosing group.
     *
     * @return list<Fragment>
     *
     * @throws SqlError When a flag or the body is not valid
     */
    public function flagged(string $first, Mode $saved): array
    {
        $open = $this->flags($first);
        if ($open === null) {
            return [Fragment::empty($this->translator->mode->caseless ? '(?i)' : '(?-i)')];
        }

        return $this->close($open, $saved);
    }

    /**
     * Reads the name of a named group up to its closing bracket, and numbers the group.
     *
     * @throws SqlError When the name is not valid or is taken
     */
    public function name(): void
    {
        $scanner = $this->translator->scanner;
        $character = $scanner->next();
        if ($character === null || preg_match('/\A[A-Za-z]\z/', $character) !== 1) {
            throw $scanner->syntax();
        }
        $name = $character;
        while (($character = $scanner->next()) !== '>') {
            if ($character === null || preg_match('/\A[A-Za-z0-9]\z/', $character) !== 1) {
                throw DataError::RegexpInvalidCaptureGroupName->error();
            }
            $name .= $character;
        }
        if (isset($this->translator->names[$name])) {
            throw DataError::RegexpInvalidCaptureGroupName->error();
        }
        $this->translator->groups++;
        $this->translator->names[$name] = $this->translator->groups;
    }

    /**
     * Skips a (?#..) comment up to its closing parenthesis; a comment gives no term.
     *
     * @return list<Fragment>
     *
     * @throws SqlError When the comment is not closed
     */
    public function comment(): array
    {
        while (($character = $this->translator->scanner->next()) !== ')') {
            if ($character === null) {
                throw DataError::RegexpMismatchedParenthesis->error();
            }
        }

        return [];
    }

    /**
     * Reads flags from their first letter: answers the opening of a group they scope, or null when they end with a parenthesis and hold for the rest of the group.
     *
     * @throws SqlError When a flag is not valid
     */
    public function flags(string $first): ?string
    {
        if (!in_array($first, self::FLAGS, true) && $first !== '-') {
            throw $this->translator->scanner->syntax();
        }
        $mode = $this->translator->mode;
        $on = true;
        $character = $first;
        while ($character !== ')' && $character !== ':') {
            if ($character === '-') {
                $on = false;
            } elseif ($character !== null && in_array($character, self::FLAGS, true)) {
                $mode = $mode->with($character, $on);
            } else {
                throw DataError::RegexpInvalidFlag->error();
            }
            $character = $this->translator->scanner->next();
        }
        $this->translator->mode = $mode;

        return $character === ':' ? '(?' . ($mode->caseless ? 'i' : '-i') . ':' : null;
    }
}
