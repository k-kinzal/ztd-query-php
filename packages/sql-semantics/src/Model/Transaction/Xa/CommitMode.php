<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

/**
 * Whether the transaction manager requests a one-phase or prepared commit.
 * @visibility public
 * @example Inspecting the alternative
 *     \SqlSemantics\Model\Transaction\Xa\CommitMode::OnePhase->value // => 'ONE PHASE'
 */
enum CommitMode: string
{
    case Prepared = '';
    case OnePhase = 'ONE PHASE';
}
