<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A construct the grammar accepts and the server rejects as not implemented.
 *
 * Source: https://www.postgresql.org/docs/17/unsupported-features-sql-standard.html.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotImplemented('UNIQUE predicate'))->message() // => 'UNIQUE predicate is not yet implemented.'
 */
final class NotImplemented implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $feature The construct
     */
    public function __construct(public readonly string $feature)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return $this->feature . ' is not yet implemented.';
    }
}
