<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An INTO clause whose completed EXPLAIN options cannot produce a stored plan.
 *
 * MySQL 8.1 refuses ANALYZE with INTO. Other refused combinations lack an
 * explicit JSON format. The diagnostic retains decoded operands, never SQL or a parser node.
 *
 * @visibility public
 * @example Describing a missing JSON format
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto())->message() // => 'EXPLAIN INTO requires FORMAT=JSON'
 */
final class RefusedExplainInto implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name|null $format The explicit format, or null for an implicit format
     * @param bool $analyze Whether the release refuses the ANALYZE/INTO combination first
     */
    public function __construct(public readonly ?Name $format = null, public readonly bool $analyze = false)
    {
        Check::input($analyze || strcasecmp($format->value ?? '', 'JSON') !== 0, 'A JSON INTO format has no format refusal.');
    }

    /**
     * Describes the incompatible options.
     */
    public function message(): string
    {
        return $this->analyze ? 'EXPLAIN ANALYZE does not support the INTO clause.' : UtilityRule::ExplainIntoFormat->value;
    }
}
