<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An explicit signed integer or SQL NULL for one supplied position.
 *
 * @visibility public
 * @example Writing an explicit NULL cell
 *     (new \SqlSemantics\Platform\MySql\Ir\Mutation\InsertValue(null))->toString() // => 'NULL'
 */
final class InsertValue
{
    use Snapshot;

    /**
     * Fixes an explicit integer or NULL cell.
     */
    public function __construct(public readonly ?IntegerLiteral $value)
    {
    }

    /**
     * Writes one cell for the declared signed integer target.
     */
    public function toString(): string
    {
        return $this->value === null ? 'NULL' : $this->value->decimal;
    }
}
