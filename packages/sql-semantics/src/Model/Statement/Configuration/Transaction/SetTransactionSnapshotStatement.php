<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Transaction;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests importing a named PostgreSQL snapshot without retrieving snapshot data.
 * @visibility public
 * @example Inspecting the transaction request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT 'snapshot-id'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Transaction\SetTransactionSnapshotStatement // => true
 */
final class SetTransactionSnapshotStatement extends ConfigurationStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Literal $snapshot, public readonly Locality $locality = Locality::Session)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SetTransactionSnapshotStatement requires PostgreSql.');
        }
        if ($snapshot->type->dialect !== Dialect::PostgreSql || $snapshot->literalKind !== \SqlSemantics\Model\Scalar\Value\LiteralKind::Text) {
            throw new InvalidStructure('A snapshot identifier requires a PostgreSQL text literal.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the transaction request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->snapshot, $this->locality);
    }

    /**
     * Replaces snapshot in a new validated transaction request.
     */
    public function withSnapshot(Literal $snapshot): self
    {
        return $this->changed(new self($this->origin, $snapshot, $this->locality));
    }

    /**
     * Replaces locality in a new validated transaction request.
     */
    public function withLocality(Locality $locality): self
    {
        return $this->changed(new self($this->origin, $this->snapshot, $locality));
    }
}
