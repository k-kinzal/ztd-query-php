<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Control;

/**
 * The transaction effect requested by a SQLite trigger error.
 * @visibility public
 */
enum RaiseAction: string
{
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
}
