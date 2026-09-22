<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public

 */
enum Chaining: string
{
    case Default = 'default';
    case Chain = 'chain';
    case NoChain = 'no-chain';
}
