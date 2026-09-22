<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

/**
 * The before or after row image supplied to a trigger.
 * @visibility public
 */
enum RowVersion: string
{
    case Old = 'old';
    case New = 'new';
}
