<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A statement that writes a generated column, which SQLite rejects.
 *
 * The value of a generated column is computed from the other columns of its
 * row, so an INSERT cannot name it in its column list and an UPDATE or an
 * upsert cannot assign it (SQLITE-GENERATED-WRITE-001). The column is named
 * as it is declared, as SQLite does in its message.
 * Source: https://sqlite.org/gencol.html.
 *
 * @visibility SqlSemantics
 */
final class GeneratedColumnWrite implements Diagnostic
{
    use Snapshot;

    /**
     * @param WriteKind $write How the statement writes the column
     * @param Name $column The generated column, as declared
     */
    public function __construct(public readonly WriteKind $write, public readonly Name $column)
    {
    }

    /**
     * Describes the problem in the words of SQLite.
     */
    public function message(): string
    {
        return $this->write->value . ' "' . $this->column->value . '"';
    }
}
