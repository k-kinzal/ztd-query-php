<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

/**
 * What happens to a temporary table at the end of each transaction.
 *
 * Mirrors `OnCommitAction` (`ONCOMMIT_DROP`, `DELETE_ROWS`,
 * `PRESERVE_ROWS`). PRESERVE ROWS is the default and is kept when written.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the ON COMMIT action
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TEMP TABLE t (a int) ON COMMIT DELETE ROWS');
 *     $create->statement->onCommit // => \SqlSemantics\Platform\PostgreSql\Statement\Table\OnCommit::DeleteRows
 */
enum OnCommit: string
{
    case PreserveRows = 'PRESERVE ROWS';
    case DeleteRows = 'DELETE ROWS';
    case Drop = 'DROP';

    /**
     * Answers the keywords after ON COMMIT.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
