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
     * The noise positions by production signature.
     *
     * - `ecmd`: the semicolon only separates commands (https://sqlite.org/lang.html).
     * - `as: AS nm`: the AS keyword before an alias is optional and changes nothing
     *   (https://sqlite.org/syntax/result-column.html).
     */
    public const POSITIONS = [
        'ecmd: SEMI' => [0],
        'ecmd: cmdx SEMI' => [1],
        'ecmd: explain cmdx SEMI' => [2],
        'as: AS nm' => [0],
    ];
}
