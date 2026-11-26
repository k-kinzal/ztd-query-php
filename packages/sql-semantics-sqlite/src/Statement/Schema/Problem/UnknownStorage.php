<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;

/**
 * A word after the expression of a generated column that is neither VIRTUAL nor STORED.
 *
 * The grammar accepts any identifier; SQLite compares the written text with
 * `virtual` and `stored` and rejects every other word, a quoted one included.
 * Source: https://sqlite.org/gencol.html.
 *
 * @visibility SqlSemantics
 */
final class UnknownStorage implements Diagnostic
{
    use Snapshot;

    /**
     * @param Word $word The written word
     */
    public function __construct(public readonly Word $word)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Generated column storage ' . $this->word->spelling() . ' is neither VIRTUAL nor STORED.';
    }
}
