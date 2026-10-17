<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The regular expression functions of MySQL 8.0 and later: REGEXP_LIKE, REGEXP_INSTR, REGEXP_SUBSTR and REGEXP_REPLACE.
 *
 * The subject and the pattern are matched in the collation they aggregate to, case-insensitively
 * when it is a `_ci` collation, as ICU matches (see Translator); a binary string matches byte by
 * byte, each byte read as a latin1 character. A binary string with a string of another character
 * set is an error (ER_CHARACTER_SET_MISMATCH). Positions count characters from 1; REGEXP_INSTR searches
 * the text from its position as if it began there, while REGEXP_SUBSTR and REGEXP_REPLACE search
 * the whole text from it, so that lookbehind sees the text before it and ^ does not match at it. A match is searched from a position (default 1), and
 * occurrence (default 1, below 1 read as 1) picks the match; REGEXP_REPLACE replaces every match
 * when occurrence is 0, its default. REGEXP_INSTR answers the start of the match, or its end with return_option
 * 1; a return_option other than 0 or 1 is an error (ER_WRONG_ARGUMENTS) that precedes the others. A position after the end is an error (ER_REGEXP_INDEX_OUTOFBOUNDS_ERROR):
 * past the last character for REGEXP_INSTR of a non-empty subject, past the end for the others;
 * a position below 1 is ER_WRONG_PARAMETERS_TO_NATIVE_FCT for REGEXP_SUBSTR and REGEXP_REPLACE.
 * A NULL argument gives NULL, after the match_type, the pattern and (for REGEXP_SUBSTR and
 * REGEXP_REPLACE) the position below 1 are checked in that order (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Patterns
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('REGEXP_LIKE', 2, 3, $this->like(...), 1, $this->resolveLike(...)),
            new Routine('REGEXP_INSTR', 2, 6, $this->instr(...), 1, $this->resolveInstr(...)),
            new Routine('REGEXP_SUBSTR', 2, 5, $this->substr(...), 1, $this->resolveSubstr(...)),
            new Routine('REGEXP_REPLACE', 3, 6, $this->replace(...), 1, $this->resolveReplace(...)),
        ];
    }

    /**
     * Checks a call of REGEXP_LIKE when it is compiled: the character sets of the subject and the pattern.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known when the call is compiled
     * @return list<Evaluable>
     *
     * @throws SqlError When a binary string meets a string of another character set
     */
    public function resolveLike(Frame $frame, array $arguments, array $known): array
    {
        $this->check([$arguments[0], $arguments[1]], 'regexp_like');

        return $arguments;
    }

    /**
     * Checks a call of REGEXP_INSTR when it is compiled: the character sets, and a return_option known then.
     *
     * A return_option known then is read then, and again for each row (verified on a live 8.4 server).
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known when the call is compiled
     * @return list<Evaluable>
     *
     * @throws SqlError When a binary string meets a string of another character set, or the return_option is neither 0 nor 1
     */
    public function resolveInstr(Frame $frame, array $arguments, array $known): array
    {
        $this->check([$arguments[0], $arguments[1]], 'regexp_instr');
        if (!isset($arguments[4]) || !($known[4] ?? false)) {
            return $arguments;
        }
        $value = $arguments[4]->evaluate($frame);
        $option = new Constant($arguments[4]->domain(), $value);
        $this->option($frame, $option);
        $arguments[4] = $option;

        return $arguments;
    }

    /**
     * Checks a call of REGEXP_SUBSTR when it is compiled: the character sets of the subject and the pattern.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known when the call is compiled
     * @return list<Evaluable>
     *
     * @throws SqlError When a binary string meets a string of another character set
     */
    public function resolveSubstr(Frame $frame, array $arguments, array $known): array
    {
        $this->check([$arguments[0], $arguments[1]], 'regexp_substr');

        return $arguments;
    }

    /**
     * Checks a call of REGEXP_REPLACE when it is compiled: the character sets of the subject, the pattern and the replacement.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known when the call is compiled
     * @return list<Evaluable>
     *
     * @throws SqlError When a binary string meets a string of another character set
     */
    public function resolveReplace(Frame $frame, array $arguments, array $known): array
    {
        $this->check([$arguments[0], $arguments[1], $arguments[2]], 'regexp_replace');

        return $arguments;
    }

    /**
     * Reads the return_option of REGEXP_INSTR, or answers null when it is NULL.
     *
     * @throws SqlError When it is neither 0 nor 1
     */
    public function option(Frame $frame, Evaluable $argument): ?int
    {
        $option = $this->integer($frame, $argument);
        if ($option !== null && $option !== 0 && $option !== 1) {
            throw StatementError::WrongArguments->error('regexp_instr: return_option must be 1 or 0.');
        }

        return $option;
    }

    /**
     * Fails when a binary string meets a string of another character set, naming the first two that differ.
     *
     * @param list<Evaluable> $arguments The string arguments that take part
     *
     * @throws SqlError When they mix binary and non-binary strings (ER_CHARACTER_SET_MISMATCH)
     */
    public function check(array $arguments, string $function): void
    {
        $sides = [];
        foreach ($arguments as $argument) {
            $domain = $argument->domain();
            if ($domain->kind === Kind::String) {
                $sides[] = $domain->collation->charset === Charset::binary() ? 'binary' : $domain->collation->name;
            }
        }
        foreach ($sides as $side) {
            if (($side === 'binary') !== ($sides[0] === 'binary')) {
                throw DataError::CharacterSetMismatch->error($sides[0], $side, $function);
            }
        }
    }

    /**
     * Answers the collation the subject and the pattern are matched in.
     *
     * @throws SqlError When their collations do not mix
     */
    public function collation(Frame $frame, Evaluable $subject, Evaluable $pattern, string $function): Collation
    {
        $connection = Collation::named((string) $frame->context->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');

        return Collations::aggregate([$subject->domain(), $pattern->domain()], $function, $connection, true, $frame->context->modes->release)[0];
    }

    /**
     * Reads a string argument as UTF-8 through the character set it is matched in, or answers null; a binary string is read as latin1.
     */
    public function text(Frame $frame, Evaluable $argument, Collation $collation): ?string
    {
        $domain = $argument->domain();
        $text = Convert::toText($argument->evaluate($frame), $domain);
        if ($text === null) {
            return null;
        }
        $latin1 = Charset::known('latin1');
        $from = $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4');
        $from = $from === Charset::binary() ? $latin1 : $from;
        $through = $collation->charset === Charset::binary() ? $latin1 : $collation->charset;

        return Encoding::convert(Encoding::convert($text, $from, $through), $through, Charset::known('utf8mb4'));
    }

    /**
     * Writes a UTF-8 result in the character set of the result; a binary result as latin1 bytes.
     */
    public function output(string $text, Domain $result): string
    {
        $charset = $result->collation->charset === Charset::binary() ? Charset::known('latin1') : $result->collation->charset;

        return Encoding::convert($text, Charset::known('utf8mb4'), $charset);
    }

    /**
     * Reads an integer argument, or answers null.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public function integer(Frame $frame, Evaluable $argument): ?int
    {
        return Convert::toInteger($argument->evaluate($frame), $argument->domain(), $frame->context);
    }

    /**
     * Reads the match_type of a call into a mode, or answers null when it is NULL.
     *
     * @throws SqlError When match_type holds a letter that is not a flag
     */
    public function mode(Frame $frame, ?Evaluable $flags, Collation $collation, string $function): ?Mode
    {
        $text = $flags === null ? '' : Convert::toText($flags->evaluate($frame), $flags->domain());

        return $text === null ? null : Mode::of($collation, $text, $function);
    }

    /**
     * Reads and translates the pattern of a call, or answers null when it is NULL.
     *
     * @throws SqlError When the pattern is not valid
     */
    public function expression(Frame $frame, Evaluable $pattern, Collation $collation, Mode $mode): ?Expression
    {
        $text = $this->text($frame, $pattern, $collation);

        return $text === null ? null : Translator::compile($text, $mode);
    }

    /**
     * Notes that ICU read the rules of its default locale, when a match was found with grapheme clusters or Unicode word boundaries (a note, 4077).
     */
    public function located(Frame $frame, Expression $expression, bool $found): void
    {
        if ($found && $expression->located) {
            $frame->context->note(DataError::RegexpDefaultLocale);
        }
    }

    /**
     * Answers the byte offset of a character position counted from 0 in a UTF-8 text.
     */
    public function offset(string $text, int $characters): int
    {
        return strlen(mb_substr($text, 0, $characters, 'UTF-8'));
    }

    /**
     * REGEXP_LIKE(expr, pattern[, match_type]): 1 when the pattern matches the subject, else 0.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When an argument is not valid
     */
    public function like(Frame $frame, array $arguments): ?int
    {
        $collation = $this->collation($frame, $arguments[0], $arguments[1], 'regexp_like');
        $mode = $this->mode($frame, $arguments[2] ?? null, $collation, 'regexp_like');
        if ($mode === null) {
            return null;
        }
        $expression = $this->expression($frame, $arguments[1], $collation, $mode);
        if ($expression === null) {
            return null;
        }
        $subject = $this->text($frame, $arguments[0], $collation);
        if ($subject === null) {
            return null;
        }

        $found = $expression->find($subject, 0) !== null;
        $this->located($frame, $expression, $found);

        return $found ? 1 : 0;
    }

    /**
     * REGEXP_INSTR(expr, pattern[, pos[, occurrence[, return_option[, match_type]]]]): the position of a match, or 0.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When an argument is not valid
     */
    public function instr(Frame $frame, array $arguments): ?int
    {
        $option = isset($arguments[4]) ? $this->option($frame, $arguments[4]) : 0;
        $collation = $this->collation($frame, $arguments[0], $arguments[1], 'regexp_instr');
        $mode = $this->mode($frame, $arguments[5] ?? null, $collation, 'regexp_instr');
        $expression = $mode === null ? null : $this->expression($frame, $arguments[1], $collation, $mode);
        $subject = $expression === null ? null : $this->text($frame, $arguments[0], $collation);
        $position = $subject === null ? null : (isset($arguments[2]) ? $this->integer($frame, $arguments[2]) : 1);
        $occurrence = $position === null ? null : (isset($arguments[3]) ? $this->integer($frame, $arguments[3]) : 1);
        if ($expression === null || $subject === null || $position === null || $occurrence === null || $option === null) {
            return null;
        }
        $length = mb_strlen($subject, 'UTF-8');
        if ($length > 0 && ($position < 1 || $position > $length)) {
            throw DataError::RegexpIndexOutOfBounds->error();
        }
        $region = substr($subject, $this->offset($subject, $position - 1));
        $matches = $expression->all($region, max(1, $occurrence));
        $this->located($frame, $expression, $matches !== []);
        $span = $matches[max(1, $occurrence) - 1][0] ?? null;
        if ($span === null) {
            return 0;
        }

        return $position + mb_strlen(substr($region, 0, $span[$option]), 'UTF-8');
    }

    /**
     * REGEXP_SUBSTR(expr, pattern[, pos[, occurrence[, match_type]]]): the text of a match, or NULL.
     *
     * The server searches twice, so a search that notes the locale notes it twice, and a position
     * past the end is reported twice (verified on a live 8.4 server).
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When an argument is not valid
     */
    public function substr(Frame $frame, array $arguments, Domain $result): ?string
    {
        [$expression, $subject, $position, $occurrence] = $this->search($frame, $arguments, $arguments[4] ?? null, $arguments[3] ?? null, $arguments[2] ?? null, null, 'regexp_substr', 1);
        if ($expression === null || $subject === null) {
            return null;
        }
        $matches = $expression->all($subject, max(1, $occurrence), $this->offset($subject, $position - 1));
        $this->located($frame, $expression, $matches !== []);
        $this->located($frame, $expression, $matches !== []);
        $span = $matches[max(1, $occurrence) - 1][0] ?? null;

        return $span === null ? null : $this->output(substr($subject, $span[0], $span[1] - $span[0]), $result);
    }

    /**
     * REGEXP_REPLACE(expr, pattern, replacement[, pos[, occurrence[, match_type]]]): the subject with matches replaced.
     *
     * An empty subject stays empty, even for a pattern that matches it (verified on a live 8.4 server).
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When an argument is not valid
     */
    public function replace(Frame $frame, array $arguments, Domain $result): ?string
    {
        [$expression, $subject, $position, $occurrence, $replacement] = $this->search($frame, $arguments, $arguments[5] ?? null, $arguments[4] ?? null, $arguments[3] ?? null, $arguments[2], 'regexp_replace', 0);
        if ($expression === null || $subject === null || $replacement === null) {
            return null;
        }
        if ($subject === '') {
            return '';
        }
        $matches = $expression->all($subject, $occurrence === 0 ? 0 : max(1, $occurrence), $this->offset($subject, $position - 1));
        $this->located($frame, $expression, $matches !== []);
        if ($occurrence !== 0) {
            $matches = array_slice($matches, max(1, $occurrence) - 1, 1);
        }
        $template = $matches === [] ? null : Replacement::of($replacement, $expression);
        $text = '';
        $done = 0;
        foreach ($matches as $spans) {
            [$from, $to] = $spans[0] ?? [0, 0];
            $text .= substr($subject, $done, $from - $done) . $template?->expand($subject, $spans);
            $done = $to;
        }

        return $this->output($text . substr($subject, $done), $result);
    }

    /**
     * Reads the arguments of REGEXP_SUBSTR and REGEXP_REPLACE in the order the server checks them.
     *
     * The match_type, the pattern, the occurrence and the position come first, a position below 1
     * failing; then the replacement and the subject, whose length bounds the position.
     *
     * @param list<Evaluable> $arguments
     * @param int $default The occurrence when the call gives none
     * @return array{Expression|null, string|null, int, int, string|null} The expression and the subject, both null when the result is NULL, the position, the occurrence and the replacement
     *
     * @throws SqlError When an argument is not valid
     */
    public function search(Frame $frame, array $arguments, ?Evaluable $flags, ?Evaluable $occurrence, ?Evaluable $position, ?Evaluable $replacement, string $function, int $default): array
    {
        $none = [null, null, 1, 1, null];
        $collation = $this->collation($frame, $arguments[0], $arguments[1], $function);
        $mode = $this->mode($frame, $flags, $collation, $function);
        $expression = $mode === null ? null : $this->expression($frame, $arguments[1], $collation, $mode);
        $count = $expression === null || $occurrence === null ? $default : $this->integer($frame, $occurrence);
        $start = $expression === null || $count === null || $position === null ? 1 : $this->integer($frame, $position);
        if ($expression === null || $count === null || $start === null) {
            return $none;
        }
        if ($start < 1) {
            throw QueryError::WrongParametersToNativeFunction->error($function);
        }
        $text = $replacement === null ? '' : $this->text($frame, $replacement, $collation);
        $subject = $text === null ? null : $this->text($frame, $arguments[0], $collation);
        if ($text === null || $subject === null) {
            return $none;
        }
        if ($start - 1 > mb_strlen($subject, 'UTF-8')) {
            $message = DataError::RegexpIndexOutOfBounds->message();

            throw new SqlError(DataError::RegexpIndexOutOfBounds, $message, null, $default === 1 ? [[DataError::RegexpIndexOutOfBounds->value, $message]] : []);
        }

        return [$expression, $subject, $start, $count, $text];
    }
}
