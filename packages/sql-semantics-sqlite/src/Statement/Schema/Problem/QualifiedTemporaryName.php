<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A TEMP object whose name is qualified with a schema other than `temp`.
 *
 * SQLite rejects it with "temporary table name must be unqualified".
 * Source: https://sqlite.org/lang_createtable.html.
 *
 * @visibility SqlSemantics
 */
final class QualifiedTemporaryName implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The qualified name of the temporary object
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Temporary object ' . $this->name->name->value . ' must not be qualified with schema ' . ($this->name->schema->value ?? '') . '.';
    }
}
