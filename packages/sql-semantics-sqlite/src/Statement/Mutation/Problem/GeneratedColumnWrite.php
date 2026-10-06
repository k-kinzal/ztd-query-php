<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A statement that writes a generated column, which SQLite rejects.
 *
 * The value of a generated column is computed from the other columns of its
 * row, so an INSERT cannot name it in its column list and an UPDATE or an
 * upsert cannot assign it (SQLITE-GENERATED-WRITE-001). The column is named
 * as it is declared, as SQLite does in its message.
 * Source: https://sqlite.org/gencol.html.
 *
 * @visibility public
 * @example Reporting an UPDATE of a generated column
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a, b AS (a + 1))');
 *     $semantics->analyze('UPDATE t SET B = 1', [$table])->facts->diagnostics[0]->message() // => 'cannot UPDATE generated column "b"'
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
