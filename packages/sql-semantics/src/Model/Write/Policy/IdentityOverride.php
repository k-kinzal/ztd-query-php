<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * IdentityOverride alternatives.
 *
 * @visibility public
 */
enum IdentityOverride: string
{
    case Default = '';
    case System = 'SYSTEM';
    case User = 'USER';
}
