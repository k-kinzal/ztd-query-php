<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Locking;

/**
 * PostgreSQL relation-level conflict modes, independent of row-lock strengths.
 * @visibility public
 * @example Reading a relation lock mode
 *     \SqlSemantics\Model\Locking\PostgreSqlLockMode::ShareRowExclusive->value // => 'SHARE ROW EXCLUSIVE'
 */
enum PostgreSqlLockMode: string
{
    case AccessShare = 'ACCESS SHARE';
    case RowShare = 'ROW SHARE';
    case RowExclusive = 'ROW EXCLUSIVE';
    case ShareUpdateExclusive = 'SHARE UPDATE EXCLUSIVE';
    case Share = 'SHARE';
    case ShareRowExclusive = 'SHARE ROW EXCLUSIVE';
    case Exclusive = 'EXCLUSIVE';
    case AccessExclusive = 'ACCESS EXCLUSIVE';
}
