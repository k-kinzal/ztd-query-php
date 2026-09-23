<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

/**
 * Requests whether storage DDL waits for completion.
 * @visibility public
 * @example Selecting an asynchronous request
 *     \SqlSemantics\Model\Definition\Storage\CompletionWait::NoWait->value // => 'NO_WAIT'
 */
enum CompletionWait: string
{
    case Wait = 'WAIT';
    case NoWait = 'NO_WAIT';
}
