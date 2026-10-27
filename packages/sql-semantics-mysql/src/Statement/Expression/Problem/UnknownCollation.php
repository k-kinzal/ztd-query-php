<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

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
 *     $query->diagnostics()[0]->message() // => "Unknown collation: 'klingon_ci'"
 */
final class UnknownCollation implements Diagnostic
{
    use Snapshot;

    /**
     * @param \SqlSemantics\Statement\Operand|null $operand The prepared operand whose explicit collation lookup failed
     * @param string $name The name as written, including an empty quoted name
     */
    public function __construct(public readonly string $name, public readonly ?\SqlSemantics\Statement\Operand $operand = null)
    {
    }

    /**
     * Describes the problem as the server does, quoting at most the first 64 bytes of the name.
     */
    public function message(): string
    {
        return sprintf("Unknown collation: '%s'", substr($this->name, 0, 64));
    }
}
