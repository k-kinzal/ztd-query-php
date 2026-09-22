<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

/**
 * The declared response to a referenced row being changed or deleted.
 *
 * @example Reading a referential action
 *     \SqlSemantics\Schema\ReferentialAction::Cascade->value // => 'cascade'
 *
 * @visibility public
 */
enum ReferentialAction: string
{
    case NoAction = 'no-action';
    case Restrict = 'restrict';
    case Cascade = 'cascade';
    case SetNull = 'set-null';
    case SetDefault = 'set-default';
}
