<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction\Xa;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Xa\TransactionId;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rolls back one required XA transaction branch.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("XA ROLLBACK 'global'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Transaction\Xa\XaRollbackStatement // => true
 */
final class XaRollbackStatement extends BoundStatement
{
    /**
     * Records the requested operation without inspecting or changing live transaction state.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TransactionId $transactionId)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('XA transaction control requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::XaRollback;
    }

    /**
     * Retains transaction operands when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->transactionId);
    }

    /**
     * Replaces the required transaction identifier in a new validated snapshot.
     */
    public function withTransactionId(TransactionId $transactionId): self
    {
        return $this->changed(new self($this->origin, $transactionId));
    }
}
