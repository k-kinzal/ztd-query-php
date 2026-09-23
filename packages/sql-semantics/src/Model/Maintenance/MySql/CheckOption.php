<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

/**
 * A table check requested from the storage engine.
 * @visibility public
 * @example Reading the request domain
 *     \SqlSemantics\Model\Maintenance\MySql\CheckOption::Quick->value // => 'QUICK'
 */
enum CheckOption: string
{
    case Quick = 'QUICK';
    case Fast = 'FAST';
    case Medium = 'MEDIUM';
    case Extended = 'EXTENDED';
    case Changed = 'CHANGED';
    case Upgrade = 'FOR UPGRADE';
}
