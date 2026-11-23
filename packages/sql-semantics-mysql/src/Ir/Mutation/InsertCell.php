<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native integer, text literal or SQL NULL for one supplied position.
 *
 * @visibility public
 * @example Writing an explicit NULL cell
 *     (new \SqlSemantics\Platform\MySql\Ir\Mutation\InsertCell(null))->toString() // => 'NULL'
 */
final class InsertCell
{
    use Snapshot;

    /**
     * Fixes one explicit native literal.
     */
    public function __construct(public readonly IntegerLiteral|TextLiteral|null $value)
    {
    }

    /**
     * Renders the native literal at the declared destination.
     */
    public function toString(): string
    {
        $value = $this->value;

        return match (true) {
            $value instanceof TextLiteral => $value->toString(),
            $value instanceof IntegerLiteral => $value->decimal,
            $value === null => 'NULL',
        };
    }
}
