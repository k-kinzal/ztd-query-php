<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

/**
 * How an XA branch is disassociated from the current session.
 * @visibility public
 * @example Inspecting the alternative
 *     \SqlSemantics\Model\Transaction\Xa\EndMode::Migrate->value // => 'SUSPEND FOR MIGRATE'
 */
enum EndMode: string
{
    case End = '';
    case Suspend = 'SUSPEND';
    case Migrate = 'SUSPEND FOR MIGRATE';
}
