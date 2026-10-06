<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An ALTER TABLE request SQLite refuses because of what it adds or drops.
 *
 * Source: https://sqlite.org/lang_altertable.html.
 *
 * @visibility public
 * @example Reporting an added UNIQUE column
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('ALTER TABLE t ADD COLUMN code TEXT UNIQUE');
 *     $alter->facts->diagnostics[0]->message() // => 'A UNIQUE column cannot be added.'
 */
final class AlterationRefused implements Diagnostic
{
    use Snapshot;

    /**
     * @param AlterationObstacle $obstacle Why the request is refused
     */
    public function __construct(public readonly AlterationObstacle $obstacle)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->obstacle->value;
    }
}
