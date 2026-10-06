<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;

/**
 * Derives the declared type SQLite records for a column from the type name written in its definition.
 *
 * Rule: SQLITE-TYPE-RECORDING-001. SQLite does not decode the type name of a
 * column; it records its written text and then, in this order:
 * (1) when the text is at least 16 characters long and ends in `always`, it
 * drops that word and a `generated` before it, because the parser reads the
 * keywords GENERATED ALWAYS as type words;
 * (2) when the rest is at least 3 characters long and is one quoted word, it
 * removes the quotes, and when the result is a standard type name the column
 * has that standard type;
 * (3) otherwise, when the text starts with a quote character, it keeps only
 * the content of that first quoted run and drops everything after it.
 * The affinity is derived from the result. An absent type name, and one that
 * step 1 empties, is no declared type (BLOB affinity); a type name that step 3
 * empties is an empty declared type (NUMERIC affinity).
 *
 * Remaining assumption: SQLite records the whitespace and comments written
 * between the tokens of a type name; the model describes the text of the SQL
 * it renders, with one space between words and none around or inside the argument list.
 * Terminates: one pass over the words. Source:
 * https://sqlite.org/datatype3.html#determination_of_column_affinity,
 * https://sqlite.org/stricttables.html (and `sqlite3AddColumn()` in build.c of
 * the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TypeRecording
{
    /**
     * Answers the declared type of a column with an optional written type name.
     */
    public function domain(?TypeName $type, bool $strict): ColumnDomain
    {
        $text = $type === null ? '' : $this->stripped($this->written($type));
        if ($text === '') {
            return new ColumnDomain('', $strict);
        }
        if (strlen($text) >= 3 && $this->quotedWord($text)) {
            return new ColumnDomain(substr($text, 1, -1), $strict);
        }
        if (str_contains('"\'[`', $text[0])) {
            return new ColumnDomain($this->firstRun($text), $strict, false);
        }

        return new ColumnDomain($text, $strict);
    }

    /**
     * Answers the text of a type name as the rendered SQL writes it.
     */
    public function written(TypeName $type): string
    {
        $words = [];
        foreach ($type->words as $word) {
            $words[] = $word->spelling();
        }
        $arguments = [];
        foreach ($type->arguments as $argument) {
            $number = $argument->number;
            $arguments[] = ($argument->sign->value ?? '') . match (true) {
                $number instanceof IntegerLiteral => $number->digits,
                $number instanceof HexLiteral => '0x' . $number->digits,
                default => $number->whole . ($number->fraction === null ? '' : '.' . $number->fraction) . ($number->exponent === null ? '' : 'e' . $number->exponent),
            };
        }

        return implode(' ', $words) . ($arguments === [] ? '' : '(' . implode(',', $arguments) . ')');
    }

    /**
     * Drops a trailing `always` and a `generated` before it from a text of at least 16 characters.
     */
    public function stripped(string $text): string
    {
        if (strlen($text) < 16 || strcasecmp(substr($text, -6), 'always') !== 0) {
            return $text;
        }
        $text = rtrim(substr($text, 0, -6), " \t\n\f\r");
        if (strlen($text) >= 9 && strcasecmp(substr($text, -9), 'generated') === 0) {
            $text = rtrim(substr($text, 0, -9), " \t\n\f\r");
        }

        return $text;
    }

    /**
     * Tells whether a text of at least two characters starts with a quote character and has none between its first and last character.
     */
    public function quotedWord(string $text): bool
    {
        return str_contains('"\'[`', $text[0]) && strpbrk(substr($text, 1, -1), '"\'[`') === false;
    }

    /**
     * Answers the content of the quoted run a text starts with, as SQLite unquotes a whole text.
     */
    public function firstRun(string $text): string
    {
        $quote = $text[0] === '[' ? ']' : $text[0];
        $content = '';
        for ($index = 1; $index < strlen($text); $index++) {
            if ($text[$index] !== $quote) {
                $content .= $text[$index];
            } elseif (($text[$index + 1] ?? '') === $quote) {
                $content .= $quote;
                $index++;
            } else {
                break;
            }
        }

        return $content;
    }
}
