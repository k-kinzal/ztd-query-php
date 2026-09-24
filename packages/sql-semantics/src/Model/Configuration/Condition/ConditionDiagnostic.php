<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

/**
 * Copies one condition information item into a user variable.
 * @visibility public
 * @example Reading the target and item
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @text = MESSAGE_TEXT');
 *     [$statement->items[0]->variable, $statement->items[0]->item->value] // => ['text', 'MESSAGE_TEXT']
 */
final class ConditionDiagnostic
{
    /**
     * @param string $variable Name of the user variable receiving the item
     */
    public function __construct(public readonly string $variable, public readonly ConditionItem $item)
    {
    }
}
