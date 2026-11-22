<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A window function or window specification that uses a part of the syntax the server does not support.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-restrictions.html.
 *
 * @visibility SqlSemantics
 */
final class UnsupportedWindowing implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowingLimit $limit The unsupported part
     */
    public function __construct(public readonly WindowingLimit $limit)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "This version of MySQL doesn't yet support '" . $this->limit->value . "'";
    }
}
