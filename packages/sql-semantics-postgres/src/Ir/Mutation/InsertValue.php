<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An explicit signed integer or SQL NULL for one supplied position.
 *
 * @visibility public
 * @example Writing an explicit NULL cell
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertValue(null))->toString() // => 'NULL'
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
        return $this->value === null ? 'NULL' : 'CAST(' . $this->value->decimal . ' AS pg_catalog.int4)';
    }
}
