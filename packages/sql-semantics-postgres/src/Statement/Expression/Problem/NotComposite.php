<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A field selected from a value that is not of a composite type.
 *
 * Source: https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-ACCESSING.
 *
 * @visibility SqlSemantics
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotComposite('a', 'integer'))->message() // => 'Column notation .a applied to type integer, which is not a composite type.'
 */
final class NotComposite implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $field The selected field
     * @param string $type The type of the value
     */
    public function __construct(public readonly string $field, public readonly string $type)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column notation .' . $this->field . ' applied to type ' . $this->type . ', which is not a composite type.';
    }
}
