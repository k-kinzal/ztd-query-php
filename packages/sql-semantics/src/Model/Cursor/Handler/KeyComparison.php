<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor\Handler;

/**
 * How a HANDLER key lookup positions on an index relative to the given key.
 * @visibility public
 * @example Reading the comparison operator
 *     \SqlSemantics\Model\Cursor\Handler\KeyComparison::AtLeast->value // => '>='
 */
enum KeyComparison: string
{
    case Equal = '=';
    case AtLeast = '>=';
    case AtMost = '<=';
    case After = '>';
    case Before = '<';
}
