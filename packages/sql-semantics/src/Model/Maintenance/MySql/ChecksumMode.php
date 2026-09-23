<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

/**
 * Selects stored or row-scanned table checksums.
 * @visibility public
 * @example Reading the request domain
 *     \SqlSemantics\Model\Maintenance\MySql\ChecksumMode::Automatic->value // => ''
 */
enum ChecksumMode: string
{
    case Automatic = '';
    case Stored = 'QUICK';
    case Scan = 'EXTENDED';
}
