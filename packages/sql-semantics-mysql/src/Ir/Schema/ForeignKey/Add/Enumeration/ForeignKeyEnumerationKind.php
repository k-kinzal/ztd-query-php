<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration;

/**
 * The native label-based field roles that use an unsigned InnoDB key.
 * @visibility public
 * @example Reading the role of a member mask
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationKind::Set->value // => 'SET'
 */
enum ForeignKeyEnumerationKind: string
{
    case Enum = 'ENUM';
    case Set = 'SET';
}
