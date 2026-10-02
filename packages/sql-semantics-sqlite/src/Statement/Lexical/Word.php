<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Lexical;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\BareWords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A word at a position where SQLite looks at the written text: the name and how it is quoted.
 *
 * At most positions a quoted and an unquoted spelling of a name mean the
 * same. At a few they do not: SQLite records a declared column type with its
 * quotes, accepts the table options and the storage word of a generated
 * column only without quotes, and reads an unquoted TRUE or FALSE default as a
 * number. A word keeps the quoting so that those meanings survive.
 *
 * Rule: SQLITE-WORD-001. The spelling is the name between its delimiters with
 * each embedded closing delimiter doubled; a bracketed word cannot contain
 * `]`. Source: https://sqlite.org/lang_keywords.html. Status: Implemented.
 *
 * @visibility public
 * @example Spelling a quoted word
 *     $word = new \SqlSemantics\Platform\Sqlite\Statement\Lexical\Word(new \SqlSemantics\Statement\Identifier\Name('big "int"'), \SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote::Double);
 *     $word->spelling() // => '"big ""int"""'
 * @example Refusing an unquoted word that is a reserved keyword
 *     new \SqlSemantics\Platform\Sqlite\Statement\Lexical\Word(new \SqlSemantics\Statement\Identifier\Name('select')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an unquoted word that is not one identifier
 *     new \SqlSemantics\Platform\Sqlite\Statement\Lexical\Word(new \SqlSemantics\Statement\Identifier\Name('two words')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a bracketed word that contains a closing bracket
 *     new \SqlSemantics\Platform\Sqlite\Statement\Lexical\Word(new \SqlSemantics\Statement\Identifier\Name('a]b'), \SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote::Bracket) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Word implements Node
{
    use Snapshot;

    /**
     * @param Name $name The decoded word
     * @param WordQuote $quote How the word is delimited
     */
    public function __construct(public readonly Name $name, public readonly WordQuote $quote = WordQuote::Bare)
    {
        Check::input($quote !== WordQuote::Bare || (new BareWords())->usable($name->value), 'An unquoted word is one identifier that is not a reserved keyword.');
        Check::input($quote !== WordQuote::Bracket || !str_contains($name->value, ']'), 'A bracketed word cannot contain a closing bracket.');
    }

    /**
     * Tells whether the word is written without quotes.
     */
    public function bare(): bool
    {
        return $this->quote === WordQuote::Bare;
    }

    /**
     * Tells whether the unquoted word equals a keyword, compared without regard to ASCII case as SQLite compares it.
     */
    public function is(string $keyword): bool
    {
        return $this->bare() && strcasecmp($this->name->value, $keyword) === 0;
    }

    /**
     * Answers the exact text of the word as SQLite reads it.
     */
    public function spelling(): string
    {
        return match ($this->quote) {
            WordQuote::Bare => $this->name->value,
            WordQuote::Single => "'" . str_replace("'", "''", $this->name->value) . "'",
            WordQuote::Double => '"' . str_replace('"', '""', $this->name->value) . '"',
            WordQuote::Backtick => '`' . str_replace('`', '``', $this->name->value) . '`',
            WordQuote::Bracket => '[' . $this->name->value . ']',
        };
    }

    /**
     * Writes the word with its own quoting.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->spelling());
    }
}
