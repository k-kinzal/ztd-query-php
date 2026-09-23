<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

/**
 * Whether process inspection returns the first 100 characters or the complete active SQL text.
 * @visibility public
 * @example Requesting complete query text
 *     \SqlSemantics\Model\Query\Inspection\ProcessQueryText::Complete->value // => 'FULL'
 */
enum ProcessQueryText: string
{
    case Preview = '';
    case Complete = 'FULL';
}
