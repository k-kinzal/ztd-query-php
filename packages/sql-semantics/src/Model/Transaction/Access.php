<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public

 */
enum Access: string
{
    case ReadOnly = 'READ ONLY';
    case ReadWrite = 'READ WRITE';
}
