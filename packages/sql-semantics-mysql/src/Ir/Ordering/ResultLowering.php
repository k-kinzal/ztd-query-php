<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\Limit as ParsedLimit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

/**
 * Lowers ordered result positions and resolved integer bounds.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ResultLowering
{
    /**
     * Resolves the positional key established by binding.
     */
    public function order(OrderItem $key): Order
    {
        Check::input($key->expression instanceof OutputOrdinal, 'ORDER BY requires a supported output position.');

        return new Order($key->expression->position(), $key->direction === Direction::Descending);
    }

    /**
     * Assigns the count and offset by their meaning in the parsed clause.
     */
    public function limit(?ParsedLimit $clause): ?Limit
    {
        if ($clause === null) {
            return null;
        }
        Check::input($clause instanceof RowLimit, 'A row bound requires a native LIMIT clause.');
        $count = $this->bound($clause->count);
        $offset = $clause->offset === null ? 0 : $this->bound($clause->offset);

        return new Limit($count, $offset);
    }

    /**
     * Resolves an unsigned decimal operand while retaining its full checked value.
     */
    public function bound(Scalar $operand): int
    {
        Check::input($operand instanceof NumberLiteral, 'A row bound requires a literal integer.');
        $digits = ltrim($operand->text, '0');
        $value = new IntegerLiteral($digits === '' ? '0' : $digits);

        return (int) $value->decimal;
    }
}
