<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

/**
 * The semantic role of one table-checksum result.
 * @visibility public
 * @example Reading the request domain
 *     \SqlSemantics\Model\Maintenance\MySql\ChecksumField::Table->value // => 'Table'
 */
enum ChecksumField: string
{
    case Table = 'Table';
    case Checksum = 'Checksum';
}
