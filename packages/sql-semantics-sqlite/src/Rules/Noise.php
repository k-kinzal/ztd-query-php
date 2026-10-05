<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

/**
 * The token positions of SQLite productions that have no influence on meaning.
 *
 * Each entry names a production and the positions of its noise tokens, with
 * the reason. Nothing else may be skipped by the token correspondence check.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Noise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `ecmd`: the semicolon only separates commands (https://sqlite.org/lang.html).
     * - `setlist`: the assignment sign after a column or a column list is mandatory
     *   punctuation; the tokenizer reads `=` and `==` as the same token there and
     *   neither spelling changes the assignment (https://sqlite.org/lang_update.html).
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'ecmd: SEMI' => [0],
            'ecmd: cmdx SEMI' => [1],
            'ecmd: explain cmdx SEMI' => [2],
            'setlist: setlist COMMA nm EQ expr' => [3],
            'setlist: setlist COMMA LP idlist RP EQ expr' => [5],
            'setlist: nm EQ expr' => [1],
            'setlist: LP idlist RP EQ expr' => [3],
        ];
    }

    /**
     * Answers the terminals whose spellings are one keyword, with the spelling the comparison uses.
     *
     * - `TEMP`: TEMP and TEMPORARY are two spellings of the same keyword
     *   (https://sqlite.org/lang_createtable.html).
     *
     * @return array<string, string>
     */
    public static function synonyms(): array
    {
        return ['TEMP' => 'TEMP'];
    }
}
