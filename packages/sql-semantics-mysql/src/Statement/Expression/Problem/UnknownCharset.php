<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A character set the server does not have (`ER_UNKNOWN_CHARACTER_SET`, error 1115).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_unknown_character_set.
 *
 * @visibility public
 * @example Reporting an unknown character set
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT CONVERT('a' USING klingon)");
 *     $query->facts->diagnostics[0]->message() // => "Unknown character set: 'klingon'"
 */
final class UnknownCharset implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $name The name as written
     */
    public function __construct(public readonly string $name)
    {
        Check::input($name !== '', 'A character set is named.');
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf("Unknown character set: '%s'", $this->name);
    }
}
