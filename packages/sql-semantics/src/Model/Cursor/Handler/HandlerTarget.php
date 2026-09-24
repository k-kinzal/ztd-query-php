<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor\Handler;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the open handler a HANDLER operation names: one unaliased name resolved as a table, and a MySQL read condition.
 * @visibility SqlSemantics
 */
final class HandlerTarget
{
    /**
     * @param Expression|null $where Row condition of a read; null when the operation has none
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, TableReference $handler, ?Expression $where = null): void
    {
        if ($origin->dialect !== Dialect::MySql || ($where !== null && $where->type->dialect !== Dialect::MySql)) {
            throw new InvalidStructure('HANDLER requires MySQL operands.');
        }
        if ($handler->alias !== null) {
            throw new InvalidStructure('An open handler is named by its alias alone.');
        }
        StatementOperands::relation($handler, $origin->dialect);
    }
}
