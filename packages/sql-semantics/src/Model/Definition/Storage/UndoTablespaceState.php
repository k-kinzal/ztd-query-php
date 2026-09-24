<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

/**
 * Selects whether an undo tablespace serves new transactions.
 * @visibility public
 * @example Naming the retired state
 *     \SqlSemantics\Model\Definition\Storage\UndoTablespaceState::Inactive->value // => 'INACTIVE'
 */
enum UndoTablespaceState: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
