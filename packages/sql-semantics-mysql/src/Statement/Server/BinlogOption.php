<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server;

/**
 * The two written forms that suppress binary logging of a maintenance request.
 *
 * Absence is represented by null on the owning statement. Both alternatives
 * suppress logging while retaining the keyword requested by the caller.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 *
 * @visibility public
 * @example Selecting local maintenance
 *     \SqlSemantics\Platform\MySql\Statement\Server\BinlogOption::Local->value // => 'LOCAL'
 */
enum BinlogOption: string
{
    case NoWriteToBinlog = 'NO_WRITE_TO_BINLOG';
    case Local = 'LOCAL';
}
