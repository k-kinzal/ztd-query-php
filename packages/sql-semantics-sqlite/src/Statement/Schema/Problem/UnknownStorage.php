<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A word after the expression of a generated column that is neither VIRTUAL nor STORED.
 *
 * The grammar accepts any identifier; SQLite compares the written text with
 * `virtual` and `stored` and rejects every other word, a quoted one included.
 * Source: https://sqlite.org/gencol.html.
 *
 * @visibility public
 * @example Reporting an unknown storage word
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b AS (a) PERSISTED)');
 *     $create->facts->diagnostics[0]->message() // => 'Generated column storage PERSISTED is neither VIRTUAL nor STORED.'
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
