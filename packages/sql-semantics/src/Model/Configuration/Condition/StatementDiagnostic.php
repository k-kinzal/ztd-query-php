<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

/**
 * Copies one statement information item into a user variable.
 * @visibility public
 * @example Reading the target and item
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @rows = ROW_COUNT');
 *     [$statement->items[0]->variable, $statement->items[0]->item->value] // => ['rows', 'ROW_COUNT']
 */
final class StatementDiagnostic
{
    /**
     * @param string $variable Name of the user variable receiving the item
     */
    public function __construct(public readonly string $variable, public readonly StatementItem $item)
    {
    }
}
