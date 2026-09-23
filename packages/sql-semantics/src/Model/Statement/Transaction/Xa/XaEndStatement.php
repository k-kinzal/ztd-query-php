<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction\Xa;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Xa\EndMode;
use SqlSemantics\Model\Transaction\Xa\TransactionId;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Ends the session association of a required XA transaction branch.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("XA END 'global' SUSPEND FOR MIGRATE");
 *     $statement instanceof \SqlSemantics\Model\Statement\Transaction\Xa\XaEndStatement // => true
 */
final class XaEndStatement extends BoundStatement
{
    /**
     * Records the requested operation without inspecting or changing live transaction state.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TransactionId $transactionId, public readonly EndMode $mode = EndMode::End)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('XA transaction control requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::XaEnd;
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
    public function withMode(EndMode $mode): self
    {
        return $this->changed(new self($this->origin, $this->transactionId, $mode));
    }
}
