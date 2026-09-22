<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * ConstraintResponse alternatives.
 *
 * @visibility public
 */
enum ConstraintResponse: string
{
    case Default = '';
    case Rollback = 'ROLLBACK';
    case Abort = 'ABORT';
    case Fail = 'FAIL';
    case Ignore = 'IGNORE';
    case Replace = 'REPLACE';
}
