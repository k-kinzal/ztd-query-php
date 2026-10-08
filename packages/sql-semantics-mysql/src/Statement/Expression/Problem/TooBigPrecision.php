<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A fractional seconds precision above 6 for a cast (`ER_TOO_BIG_PRECISION`, error 1426).
 *
 * The server refuses it while it parses the cast, and parses no further.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_too_big_precision.
 *
 * @visibility public
 * @example Reporting a precision above 6
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT CAST(1 AS TIME(7))');
 *     $query->facts->diagnostics[0]->message() // => "Too-big precision 7 specified for 'CAST'. Maximum is 6."
 */
final class TooBigPrecision implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $precision The precision as written
     * @param string $function The function as the server names it
     */
    public function __construct(public readonly int $precision, public readonly string $function)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf("Too-big precision %d specified for '%s'. Maximum is 6.", $this->precision, $this->function);
    }
}
