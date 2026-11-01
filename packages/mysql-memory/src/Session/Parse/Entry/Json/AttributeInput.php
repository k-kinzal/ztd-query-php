<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Json;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute;

/**
 * Formats the JSON operand refusal already established by SQL Semantics.
 *
 * No JSON interpretation is performed by the consumer. Excessive nesting
 * records the depth condition before the attribute condition, as observed
 * through the native SQL interface.
 *
 * @visibility MySqlMemory
 */
final class AttributeInput
{
    /**
     * Formats the operand byte boundary and preserves secondary conditions.
     */
    public function error(RefusedJsonAttribute $problem): SqlError
    {
        $attribute = SchemaError::InvalidEngineAttribute;
        $message = substr($attribute->message($problem->reason, $problem->position, substr($problem->attribute->value, $problem->position)), 0, 511);
        return $problem->deep
            ? new SqlError(DataError::JsonDocumentTooDeep, DataError::JsonDocumentTooDeep->message(), following: [[$attribute->number(), $message]])
            : new SqlError($attribute, $message);
    }
}
