<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

/**
 * Whether this maintenance request is written to the binary log.
 * @visibility public
 * @example Reading the request domain
 *     \SqlSemantics\Model\Maintenance\MySql\BinlogPolicy::Write->value // => ''
 */
enum BinlogPolicy: string
{
    case Write = '';
    case Omit = 'NO_WRITE_TO_BINLOG';
}
