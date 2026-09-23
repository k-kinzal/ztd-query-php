<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

/**
 * Representation of the concatenated transaction identifier in recovery results.
 * @visibility public
 * @example Inspecting the alternative
 *     \SqlSemantics\Model\Transaction\Xa\RecoveryEncoding::Hexadecimal->value // => 'CONVERT XID'
 */
enum RecoveryEncoding: string
{
    case Bytes = '';
    case Hexadecimal = 'CONVERT XID';
}
