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
 * @visibility SqlSemantics
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
