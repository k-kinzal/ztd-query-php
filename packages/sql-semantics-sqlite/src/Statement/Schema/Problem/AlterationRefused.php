<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An ALTER TABLE request SQLite refuses because of what it adds or drops.
 *
 * Source: https://sqlite.org/lang_altertable.html.
 *
 * @visibility public
 * @example Reporting an added UNIQUE column
 *     (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('ALTER TABLE t ADD COLUMN code TEXT UNIQUE') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\SemanticException: A UNIQUE column cannot be added.
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
