<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Statement\Node;

/**
 * Adds the error MySQL 5.7 records for a later row of INSERT ... VALUES after the error of the statement.
 *
 * MySQL 5.7 checks the first row against the written columns, then resolves the columns, then
 * compares each later row with the first one; an error in the first two steps does not stop
 * it, so the first later row of another length is recorded after it as ER_WRONG_VALUE_COUNT_ON_ROW,
 * and the comparison stops there (verified on a live 5.7.44 server).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/insert.html.
 *
 * @visibility MySqlMemory
 */
final class ValueRows
{
    /**
     * Answers the error of a statement with the error of its first later row of another length after it, as MySQL 5.7 records them.
     */
    public function extended(SqlError $error, Node $statement, GrammarRelease $release): SqlError
    {
        if ($release !== GrammarRelease::MySql5744 || !$statement instanceof InsertRows || $error->following !== []) {
            return $error;
        }
        $first = $error->error === QueryError::BadField || ($error->error === QueryError::WrongValueCountOnRow && $error->getMessage() === QueryError::WrongValueCountOnRow->message(1));
        if (!$first || $statement->rows === []) {
            return $error;
        }
        $width = count($statement->rows[0]->values);
        foreach ($statement->rows as $index => $row) {
            if ($index > 0 && count($row->values) !== $width) {
                return new SqlError($error->error, $error->getMessage(), $error->getPrevious(), [[QueryError::WrongValueCountOnRow->value, QueryError::WrongValueCountOnRow->message($index + 1)]]);
            }
        }

        return $error;
    }
}
