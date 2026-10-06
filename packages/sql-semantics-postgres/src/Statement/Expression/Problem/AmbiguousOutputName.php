<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A name that matches several output columns where the position refers to output columns by name.
 *
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousOutputName('a'))->message() // => 'Output column reference a is ambiguous.'
 */
final class AmbiguousOutputName implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $name The name
     */
    public function __construct(public readonly string $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Output column reference ' . $this->name . ' is ambiguous.';
    }
}
