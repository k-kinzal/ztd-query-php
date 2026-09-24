<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Enumeration;

/**
 * Places a new enum label before or after an existing label.
 * @visibility public
 * @example Reading the SQL spelling of a placement
 *     \SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPlacement::After->value // => 'AFTER'
 */
enum EnumLabelPlacement: string
{
    case Before = 'BEFORE';
    case After = 'AFTER';
}
