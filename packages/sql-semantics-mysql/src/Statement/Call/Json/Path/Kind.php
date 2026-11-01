<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json\Path;

/**
 * The selection operation of one JSON path leg.
 *
 * @visibility public
 * @example Identifying an object member step
 *     \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind::Member->name // => 'Member'
 */
enum Kind
{
    case Member;
    case AnyMember;
    case Cell;
    case AnyCell;
    case Range;
    case Descendants;
}
