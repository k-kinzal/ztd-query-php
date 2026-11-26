<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

/**
 * Physical key policies recorded in a MySQL table's declaration.
 *
 * @visibility SqlSemantics
 */
enum PhysicalIndexKind: string
{
    case Index = 'INDEX';
    case Primary = 'PRIMARY KEY';
    case Unique = 'UNIQUE';
    case FullText = 'FULLTEXT';
    case Spatial = 'SPATIAL';
}
