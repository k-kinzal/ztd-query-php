<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A field a composite value does not have.
 *
 * Source: https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-ACCESSING.
 *
 * @visibility public
 * @example Reading the message
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\MissingField('f3', 'record'))->message() // => 'Column f3 not found in data type record.'
 */
final class MissingField implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $field The selected field
     * @param string $type The composite type
     */
    public function __construct(public readonly string $field, public readonly string $type)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->field . ' not found in data type ' . $this->type . '.';
    }
}
