<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\Histogram;

/**
 * Whether subsequent table analysis automatically refreshes this histogram.
 * @visibility public
 * @example Selecting the refresh policy
 *     \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy::Automatic->value // => 'AUTO UPDATE'
 */
enum RefreshPolicy: string
{
    case Default = '';
    case Manual = 'MANUAL UPDATE';
    case Automatic = 'AUTO UPDATE';
}
