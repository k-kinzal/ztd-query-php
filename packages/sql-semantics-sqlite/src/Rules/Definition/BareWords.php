<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlParser\Lexer\SourceException;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Rendering\Keywords;

/**
 * Decides whether a word can be written without quotes where SQLite reads a name.
 *
 * Rule: SQLITE-BARE-WORD-001. A bare word starts with an ASCII letter, an
 * underscore or a byte above 0x7F and continues with those, ASCII digits and
 * `$`. A keyword is usable only when the grammar lets it fall back to an
 * identifier, which is decided by parsing the word as a savepoint name.
 * Terminates: one pattern match and at most one parse of a two-word text.
 * Source: https://sqlite.org/lang_keywords.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class BareWords
{
    /**
     * @var array<string, bool>
     */
    private static array $keywords = [];

    private static ?SqliteParser $parser = null;

    /**
     * Tells whether the word is read as one identifier when written without quotes.
     */
    public function usable(string $word): bool
    {
        if (preg_match('/\A[A-Za-z_\x80-\xff][A-Za-z0-9_$\x80-\xff]*\z/', $word) !== 1) {
            return false;
        }
        if (!(new Keywords())->reserved($word)) {
            return true;
        }

        return self::$keywords[strtr($word, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')] ??= $this->accepted($word);
    }

    /**
     * Tells whether the parser accepts a keyword at a name position.
     */
    public function accepted(string $keyword): bool
    {
        self::$parser ??= new SqliteParser();
        try {
            self::$parser->parse('SAVEPOINT ' . $keyword);
        } catch (SourceException) {
            return false;
        }

        return true;
    }
}
