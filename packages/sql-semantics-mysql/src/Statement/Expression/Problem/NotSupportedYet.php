<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A form the grammar accepts and the server rejects as not yet supported (`ER_NOT_SUPPORTED_YET`, error 1235).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_not_supported_yet.
 *
 * @visibility public
 * @example Reporting CAST … AT LOCAL
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL'))->message() // => "This version of MySQL doesn't yet support 'AT LOCAL'."
 */
final class NotSupportedYet implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $feature The form, as the server names it
     */
    public function __construct(public readonly string $feature)
    {
        Check::input($feature !== '', 'An unsupported form is named.');
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return "This version of MySQL doesn't yet support '" . $this->feature . "'.";
    }
}
