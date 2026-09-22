<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

/**
 * Closed ActionKind alternatives.
 * @visibility public
 */
enum ActionKind: string
{
    case Nothing = 'nothing';
    case Update = 'update';
    case Replace = 'replace';
}
