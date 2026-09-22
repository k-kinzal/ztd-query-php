<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Format alternatives.
 *
 * @visibility public
 */
enum Format: string
{
    case Default = 'default';
    case Fixed = 'fixed';
    case Dynamic = 'dynamic';
}
