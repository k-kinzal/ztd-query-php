<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

/**
 * How an XA branch is associated with the current session.
 * @visibility public
 * @example Inspecting the alternative
 *     \SqlSemantics\Model\Transaction\Xa\StartMode::Join->value // => 'JOIN'
 */
enum StartMode: string
{
    case NewBranch = '';
    case Join = 'JOIN';
    case Resume = 'RESUME';
}
