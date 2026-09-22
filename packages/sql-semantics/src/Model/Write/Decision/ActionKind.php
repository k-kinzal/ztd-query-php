<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

/**
 * Closed ActionKind alternatives.
 * @visibility public
 */
enum ActionKind: string
{
    case Nothing = 'nothing';
    case Update = 'update';
    case Delete = 'delete';
    case Insert = 'insert';
}
