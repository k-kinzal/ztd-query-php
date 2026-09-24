<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\ResourceGroup;

/**
 * Whether threads may be assigned to a resource group.
 * @visibility public
 * @example Reading the state keyword
 *     \SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupState::Disabled->value // => 'DISABLE'
 */
enum ResourceGroupState: string
{
    case Enabled = 'ENABLE';
    case Disabled = 'DISABLE';
}
