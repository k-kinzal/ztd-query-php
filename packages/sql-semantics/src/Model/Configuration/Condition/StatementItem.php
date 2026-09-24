<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

/**
 * A statement information item of the diagnostics area, named by its SQL keyword.
 * @visibility public
 * @example Reading the item keyword
 *     \SqlSemantics\Model\Configuration\Condition\StatementItem::RowCount->value // => 'ROW_COUNT'
 */
enum StatementItem: string
{
    case Number = 'NUMBER';
    case RowCount = 'ROW_COUNT';
}
