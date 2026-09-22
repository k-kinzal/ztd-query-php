<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public

 */
enum Mode: string
{
    case Deferred = 'DEFERRED';
    case Immediate = 'IMMEDIATE';
    case Exclusive = 'EXCLUSIVE';
}
