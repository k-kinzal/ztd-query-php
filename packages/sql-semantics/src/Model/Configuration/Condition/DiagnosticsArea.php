<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

/**
 * The diagnostics area GET DIAGNOSTICS reads; CURRENT is the default.
 * @visibility public
 * @example Reading the area keyword
 *     \SqlSemantics\Model\Configuration\Condition\DiagnosticsArea::Stacked->value // => 'STACKED'
 */
enum DiagnosticsArea: string
{
    case Current = 'CURRENT';
    case Stacked = 'STACKED';
}
