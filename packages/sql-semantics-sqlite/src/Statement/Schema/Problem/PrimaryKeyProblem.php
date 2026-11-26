<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A table definition that states its primary key in a way SQLite rejects.
 *
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key.
 *
 * @visibility SqlSemantics
 */
final class PrimaryKeyProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param PrimaryKeyFlaw $flaw What is wrong with the primary key
     */
    public function __construct(public readonly PrimaryKeyFlaw $flaw)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->flaw->value;
    }
}
