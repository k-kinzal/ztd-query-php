<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A type name as SQLite reads it: one or more words and up to two signed numbers in parentheses.
 *
 * Rule: SQLITE-TYPE-NAME-001. SQLite does not look the name up. It records the
 * words as written, and when the text starts with a quoted word it keeps only
 * what that first quoted word contains. It derives a type affinity from the
 * recorded text by the five ordered substring rules of the manual and ignores
 * the numbers. The words therefore keep how they are quoted.
 * Source: https://sqlite.org/datatype3.html#determination_of_column_affinity.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the target type of a CAST
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT CAST(a AS UNSIGNED BIG INT(10))');
 *     $target = $query->statement->columns[0]->expression->target;
 *     [count($target->words), $target->text(), $target->affinity()] // => [3, 'UNSIGNED BIG INT', \SqlSemantics\Platform\Sqlite\Statement\Type\Affinity::Integer]
 */
final class TypeName implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Word> The words of the name in order
     */
    public readonly array $words;

    /**
     * @var list<SignedNumber> The numbers written in parentheses after the name
     */
    public readonly array $arguments;

    /**
     * @param list<Word> $words The words of the name in order; at least one
     * @param list<SignedNumber> $arguments The numbers written in parentheses; at most two
     */
    public function __construct(array $words, array $arguments = [])
    {
        $this->words = Check::listOf($words, Word::class, 'A type name has at least one word.', 1);
        $this->arguments = Check::listOf($arguments, SignedNumber::class, 'Type arguments are signed numbers.');
        Check::input(count($this->arguments) <= 2, 'A type name has at most two arguments.');
    }

    /**
     * Answers the text SQLite records for the words and derives the affinity from.
     */
    public function text(): string
    {
        if (!$this->words[0]->bare()) {
            return $this->words[0]->name->value;
        }
        $words = [];
        foreach ($this->words as $word) {
            $words[] = $word->spelling();
        }

        return implode(' ', $words);
    }

    /**
     * Derives the affinity by the ordered substring rules of the manual.
     */
    public function affinity(): Affinity
    {
        $text = strtr($this->text(), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');

        return match (true) {
            str_contains($text, 'INT') => Affinity::Integer,
            str_contains($text, 'CHAR'), str_contains($text, 'CLOB'), str_contains($text, 'TEXT') => Affinity::Text,
            str_contains($text, 'BLOB') => Affinity::Blob,
            str_contains($text, 'REAL'), str_contains($text, 'FLOA'), str_contains($text, 'DOUB') => Affinity::Real,
            default => Affinity::Numeric,
        };
    }

    /**
     * Writes the words and, without any space, the arguments: SQLite records the written text of a declared type.
     */
    public function render(Output $out): void
    {
        foreach ($this->words as $word) {
            $out->node($word);
        }
        foreach ($this->arguments as $position => $argument) {
            $out->glue()->symbol($position === 0 ? '(' : ',')->glue()->node($argument);
        }
        if ($this->arguments !== []) {
            $out->symbol(')');
        }
    }
}
