<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

/**
 * A support-function update that does not supply a replacement function name.
 * @visibility public
 * @example Distinguishing omission from removal
 *     \SqlSemantics\Model\Definition\Foreign\FunctionChange::Keep->value // => 'keep'
 *     \SqlSemantics\Model\Definition\Foreign\FunctionChange::Remove->value // => 'remove'
 */
enum FunctionChange: string
{
    case Keep = 'keep';
    case Remove = 'remove';
}
