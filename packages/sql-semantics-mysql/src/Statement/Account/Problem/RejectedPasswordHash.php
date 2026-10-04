<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * `IDENTIFIED BY PASSWORD 'hash'` in a MySQL 5.7 ALTER USER.
 *
 * The 5.7 grammar shares the account list of GRANT and CREATE USER with
 * ALTER USER, and the server rejects this form there with a syntax error.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/alter-user.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash())->message() // => 'ALTER USER does not accept IDENTIFIED BY PASSWORD.'
 */
final class RejectedPasswordHash implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'ALTER USER does not accept IDENTIFIED BY PASSWORD.';
    }
}
