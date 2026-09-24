<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * @visibility public
 * @example Reading the chaining of a commit
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('COMMIT AND CHAIN')->chaining // => \SqlSemantics\Model\Transaction\Chaining::Chain
 *     $binder->bind('COMMIT')->chaining // => \SqlSemantics\Model\Transaction\Chaining::Default

 */
enum Chaining: string
{
    case Default = 'default';
    case Chain = 'chain';
    case NoChain = 'no-chain';
}
