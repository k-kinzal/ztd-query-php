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
 * @visibility SqlSemantics
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
