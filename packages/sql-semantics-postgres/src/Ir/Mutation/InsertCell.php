<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One explicit signed integer, native text, native Boolean value or SQL NULL.
 *
 * @visibility public
 * @example Writing an explicit NULL cell
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertCell(null))->toString() // => 'NULL'
 */
final class InsertCell
{
    use Snapshot;

    /**
     * Stores the explicit native scalar or NULL.
     */
    public function __construct(public readonly IntegerLiteral|WideIntegerLiteral|TextLiteral|BooleanLiteral|null $value)
    {
    }

    /**
     * Emits the exact value and its native type.
     */
    public function toString(): string
    {
        return match (true) {
            $this->value instanceof IntegerLiteral => 'CAST(' . $this->value->decimal . ' AS pg_catalog.int4)',
            $this->value instanceof WideIntegerLiteral => 'CAST(' . $this->value->decimal . ' AS pg_catalog.int8)',
            $this->value instanceof TextLiteral => $this->value->toString(),
            $this->value instanceof BooleanLiteral => $this->value->value ? 'TRUE' : 'FALSE',
            $this->value === null => 'NULL',
        };
    }
}
