<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A collation the server does not have (`ER_UNKNOWN_COLLATION`, error 1273).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_unknown_collation.
 *
 * @visibility public
 * @example Reporting an unknown collation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT 'a' COLLATE klingon_ci");
 *     $query->facts->diagnostics[0]->message() // => "Unknown collation: 'klingon_ci'"
 */
final class UnknownCollation implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $name The name as written
     */
    public function __construct(public readonly string $name)
    {
        Check::input($name !== '', 'A collation is named.');
    }

    /**
     * Describes the problem as the server does, quoting at most 64 characters of the name.
     */
    public function message(): string
    {
        return sprintf("Unknown collation: '%s'", mb_substr($this->name, 0, 64));
    }
}
