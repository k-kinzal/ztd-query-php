<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A `.*` that does not end an indirection.
 *
 * Source: https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-USAGE.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\ImproperStar())->message() // => 'Improper use of "*".'
 */
final class ImproperStar implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Improper use of "*".';
    }
}
