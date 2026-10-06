<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A collation applied to a value of a type without collation support.
 *
 * Source: https://www.postgresql.org/docs/17/collation.html.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NoCollation('integer'))->message() // => 'Collations are not supported by type integer.'
 */
final class NoCollation implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $type The type of the value
     */
    public function __construct(public readonly string $type)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Collations are not supported by type ' . $this->type . '.';
    }
}
