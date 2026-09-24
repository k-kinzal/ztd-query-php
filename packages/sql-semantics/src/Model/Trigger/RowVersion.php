<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Trigger;

/**
 * The before or after row image supplied to a trigger.
 * @visibility public
 * @example Naming the row images of a trigger
 *     \SqlSemantics\Model\Trigger\RowVersion::Old->value // => 'old'
 *     \SqlSemantics\Model\Trigger\RowVersion::from('new') // => \SqlSemantics\Model\Trigger\RowVersion::New
 */
enum RowVersion: string
{
    case Old = 'old';
    case New = 'new';
}
