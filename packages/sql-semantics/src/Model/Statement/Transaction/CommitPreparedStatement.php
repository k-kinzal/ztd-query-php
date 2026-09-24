<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction;

use Override;

/**
 * Typed CommitPreparedStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Committing a prepared transaction
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMIT PREPARED 'tx1'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Transaction\CommitPreparedStatement // => true
 *     $statement->transactionId->text // => "'tx1'"
 */
final class CommitPreparedStatement extends \SqlSemantics\Model\BoundStatement
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
        return \SqlSemantics\Model\Statement\StatementKind::Commit;
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
