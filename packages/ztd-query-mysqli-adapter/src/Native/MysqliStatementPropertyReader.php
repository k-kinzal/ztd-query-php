<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Native;

use mysqli_stmt;

/**
 * Reads the native statement properties exposed by the proxy.
 */
final class MysqliStatementPropertyReader
{
    /**
     * Read a known native property without invoking a proxy property handler.
     */
    public function read(mysqli_stmt $statement, string $name): mixed
    {
        return match ($name) {
            'affected_rows' => $statement->affected_rows,
            'insert_id' => $statement->insert_id,
            'num_rows' => $statement->num_rows,
            'param_count' => $statement->param_count,
            'field_count' => $statement->field_count,
            'errno' => $statement->errno,
            'error' => $statement->error,
            'error_list' => $statement->error_list,
            'sqlstate' => $statement->sqlstate,
            'id' => $statement->id,
            default => null,
        };
    }
}
