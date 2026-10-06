<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A request SQLite rejects because it breaks a rule of the language.
 *
 * @visibility public
 * @example Reading the problem of a star without input
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT *');
 *     $query->facts->diagnostics[0]->message() // => 'no tables specified'
 */
final class Misuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param MisuseRule $rule The broken rule
     */
    public function __construct(public readonly MisuseRule $rule)
    {
    }

    /**
     * Describes the broken rule in the words of SQLite.
     */
    public function message(): string
    {
        return $this->rule->value;
    }
}
