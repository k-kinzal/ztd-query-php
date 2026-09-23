<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

/**
 * The four fields produced for each prepared XA transaction.
 * @visibility public
 * @example Inspecting the alternative
 *     \SqlSemantics\Model\Transaction\Xa\RecoveryField::GlobalLength->value // => 'gtrid_length'
 */
enum RecoveryField: string
{
    case Format = 'formatID';
    case GlobalLength = 'gtrid_length';
    case BranchLength = 'bqual_length';
    case Data = 'data';
}
