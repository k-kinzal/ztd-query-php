<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

/**
 * The written connector before a table rename destination.
 *
 * @visibility public
 * @example Reading the selected keyword
 *     \SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameMark::To->value // => 'TO'
 */
enum RenameMark: string
{
    case To = 'TO';
    case As = 'AS';
    case Equals = '=';
    case Omitted = '';
}
