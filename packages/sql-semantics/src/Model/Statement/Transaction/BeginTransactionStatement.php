<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction;

use Override;

/**
 * Typed BeginTransactionStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 */
final class BeginTransactionStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly ?\SqlSemantics\Model\Transaction\Mode $mode = null,
        public readonly \SqlSemantics\Model\Transaction\Characteristics $characteristics = new \SqlSemantics\Model\Transaction\Characteristics(),
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Begin;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->mode, $this->characteristics);
    }



}
