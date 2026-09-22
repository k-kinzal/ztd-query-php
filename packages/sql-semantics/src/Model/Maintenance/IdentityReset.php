<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance;

/**
 * Whether truncation restarts sequences owned by the selected columns.
 * @visibility public
 * @example Reading the policy
 *     \SqlSemantics\Model\Maintenance\IdentityReset::Continue->value // => 'CONTINUE IDENTITY'
 */
enum IdentityReset: string
{
    case Continue = 'CONTINUE IDENTITY';
    case Restart = 'RESTART IDENTITY';
}
