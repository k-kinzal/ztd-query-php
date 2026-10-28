<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

/**
 * The written verb releasing a prepared statement.
 *
 * @visibility public
 * @example Reading the selected keyword
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\DeallocationWord::Deallocate->value // => 'DEALLOCATE'
 */
enum DeallocationWord: string
{
    case Deallocate = 'DEALLOCATE';
    case Drop = 'DROP';
}
