<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A connection explanation with options that no connection state can satisfy.
 *
 * ANALYZE takes precedence over INTO when both are admitted by the grammar.
 * The retained value describes the refusal without retaining SQL or a parser tree.
 *
 * @visibility public
 * @example Describing an unsupported connection analysis
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain(true))->message() // => 'EXPLAIN ANALYZE FOR CONNECTION is not supported.'
 */
final class RefusedConnectionExplain implements Diagnostic
{
    use Snapshot;

    /**
     * @param bool $analyze Whether ANALYZE is present; otherwise the request has INTO
     */
    public function __construct(public readonly bool $analyze)
    {
    }

    /**
     * Describes the option without interpreting a connection identifier.
     */
    public function message(): string
    {
        return $this->analyze ? 'EXPLAIN ANALYZE FOR CONNECTION is not supported.' : 'EXPLAIN FOR CONNECTION does not support the INTO clause.';
    }
}
