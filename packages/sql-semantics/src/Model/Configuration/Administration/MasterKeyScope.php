<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

/**
 * The encryption consumer whose master key ALTER INSTANCE ROTATE replaces.
 * @visibility public
 * @example Reading the keyword
 *     \SqlSemantics\Model\Configuration\Administration\MasterKeyScope::BinaryLog->value // => 'BINLOG'
 */
enum MasterKeyScope: string
{
    case InnoDb = 'INNODB';
    case BinaryLog = 'BINLOG';
}
