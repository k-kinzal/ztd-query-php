<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

/**
 * A repair strategy requested from the storage engine.
 * @visibility public
 * @example Reading the request domain
 *     \SqlSemantics\Model\Maintenance\MySql\RepairOption::Quick->value // => 'QUICK'
 */
enum RepairOption: string
{
    case Quick = 'QUICK';
    case Extended = 'EXTENDED';
    case UseDefinitionFile = 'USE_FRM';
}
