<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction\Xa;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Xa\CommitMode;
use SqlSemantics\Model\Transaction\Xa\TransactionId;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Commits one XA branch with an explicit commit protocol.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("XA COMMIT 'global' ONE PHASE");
 *     $statement instanceof \SqlSemantics\Model\Statement\Transaction\Xa\XaCommitStatement // => true
 */
final class XaCommitStatement extends BoundStatement
{
    /**
     * Records the requested operation without inspecting or changing live transaction state.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TransactionId $transactionId, public readonly CommitMode $mode = CommitMode::Prepared)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('XA transaction control requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::XaCommit;
    }

    /**
     * Retains transaction operands when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->transactionId, $this->mode);
    }

    /**
     * Replaces the required transaction identifier in a new validated snapshot.
     */
    public function withTransactionId(TransactionId $transactionId): self
    {
        return $this->changed(new self($this->origin, $transactionId, $this->mode));
    }

    /**
     * Replaces the request policy and revalidates the complete operation.
     */
    public function withMode(CommitMode $mode): self
    {
        return $this->changed(new self($this->origin, $this->transactionId, $mode));
    }
}
