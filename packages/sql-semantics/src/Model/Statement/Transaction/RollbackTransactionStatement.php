<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction;

use Override;

/**
 * Typed RollbackTransactionStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 */
final class RollbackTransactionStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Transaction\Chaining $chaining = \SqlSemantics\Model\Transaction\Chaining::Default,
        public readonly \SqlSemantics\Model\Transaction\Release $release = \SqlSemantics\Model\Transaction\Release::Default,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Rollback;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->chaining, $this->release);
    }



}
