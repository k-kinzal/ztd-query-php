<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public

 */
enum Release: string
{
    case Default = 'default';
    case Release = 'release';
    case NoRelease = 'no-release';
}
