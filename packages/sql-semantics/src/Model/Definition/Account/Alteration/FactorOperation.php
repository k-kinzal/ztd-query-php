<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

/**
 * Whether a factor identification is added or replaces an existing factor.
 * @visibility public
 * @example Inspecting a factor operation
 *     \SqlSemantics\Model\Definition\Account\Alteration\FactorOperation::Modify->value // => 'MODIFY'
 */
enum FactorOperation: string
{
    case Add = 'ADD';
    case Modify = 'MODIFY';
}
