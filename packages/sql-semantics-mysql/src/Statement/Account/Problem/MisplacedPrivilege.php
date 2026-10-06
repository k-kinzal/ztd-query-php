<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A privilege, a column list or an object kind that GRANT or REVOKE writes at a level that does not take it.
 *
 * The server rejects the statement with the error the error field names:
 * ER_ILLEGAL_GRANT_FOR_TABLE for a column list outside a table, a routine
 * kind without a routine name, or a privilege outside TABLE_ACLS on a table
 * or PROC_ACLS on a routine; ER_WRONG_USAGE for a privilege outside DB_ACLS
 * on a database; ER_ILLEGAL_PRIVILEGE_LEVEL for a dynamic privilege outside
 * the global level (only a warning under REVOKE IF EXISTS, which is not
 * reported); ER_PARSE_ERROR for a column list on a routine.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-privilege-levels,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege('RELOAD', 'database shop', 'ER_WRONG_USAGE'))->message() // => 'RELOAD cannot be granted at database shop (ER_WRONG_USAGE).'
 */
final class MisplacedPrivilege implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $privilege The privilege, column list or object kind, as a person reads it
     * @param string $level The level as the manual names it
     * @param string $error The server error the statement fails with
     */
    public function __construct(public readonly string $privilege, public readonly string $level, public readonly string $error)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->privilege . ' cannot be granted at ' . $this->level . ' (' . $this->error . ').';
    }
}
