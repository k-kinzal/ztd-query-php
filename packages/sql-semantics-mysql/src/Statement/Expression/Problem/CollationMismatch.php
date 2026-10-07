<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A collation applied to a string of another character set (`ER_COLLATION_CHARSET_MISMATCH`, error 1253).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_collation_charset_mismatch.
 *
 * @visibility public
 * @example Applying a latin1 collation to a utf8mb4 string
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT 'a' COLLATE latin1_bin");
 *     $query->facts->diagnostics[0]->message() // => "COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb4'"
 */
final class CollationMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $collation The collation
     * @param string $charset The character set of the string
     */
    public function __construct(public readonly string $collation, public readonly string $charset)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf("COLLATION '%s' is not valid for CHARACTER SET '%s'", $this->collation, $this->charset);
    }
}
