<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

/**
 * The type of a resource group: for user threads or for system threads.
 *
 * Mirrors resourcegroups::Type.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html.
 *
 * @visibility public
 * @example Reading the keyword of a type
 *     \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind::System->value // => 'SYSTEM'
 */
enum ResourceGroupKind: string
{
    case User = 'USER';
    case System = 'SYSTEM';
}
