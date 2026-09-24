<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction;

use Override;

/**
 * Typed PrepareTransactionStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Preparing a transaction
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("PREPARE TRANSACTION 'tx1'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Transaction\PrepareTransactionStatement // => true
 *     $statement->kind->value // => 'PREPARE'
 */
final class PrepareTransactionStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Scalar\Value\Literal $transactionId,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Prepare;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->transactionId);
    }



}
