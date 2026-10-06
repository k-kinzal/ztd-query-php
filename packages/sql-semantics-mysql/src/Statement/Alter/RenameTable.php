<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\Renamings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `RENAME TABLE old TO new, …`: a request to rename tables, one rename after the other.
 *
 * Mirrors PT_rename_table_stmt (LEX of 5.x). Rule: MYSQL-RENAME-TABLE-001.
 * The renames run left to right, so a later rename sees the names the
 * earlier ones produced (`RENAME TABLE a TO tmp, b TO a, tmp TO b` swaps two
 * tables). The relation fact of each TableRenaming is the resolution of its
 * old name at that time (MYSQL-RENAMINGS-001): a name an earlier rename
 * produced resolves to that table, a name an earlier rename moved away is
 * missing. A new name that exists at that time is the diagnostic
 * TableExists. The statement changes no declaration and provides none.
 * TABLE and TABLES are synonyms.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Swapping two tables through a third name
 *     $rename = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('RENAME TABLES a TO tmp, b TO a, tmp TO b');
 *     [count($rename->statement->renamings), $rename->toString()] // => [3, 'RENAME TABLE a TO tmp, b TO a, tmp TO b']
 */
final class RenameTable implements Statement
{
    use Snapshot;

    /**
     * @var list<TableRenaming> The renames in order; at least one
     */
    public readonly array $renamings;

    /**
     * @param list<TableRenaming> $renamings The renames in order; at least one
     */
    public function __construct(array $renamings)
    {
        Check::input($renamings !== [], 'RENAME TABLE renames at least one table.');
        $this->renamings = Check::listOf($renamings, TableRenaming::class, 'RENAME TABLE holds a list of renames.');
    }

    /**
     * Records the resolution of each old name in order and reports new names that already exist.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Renamings())->derive($this->renamings, $derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RENAME', 'TABLE')->list($this->renamings);
    }
}
