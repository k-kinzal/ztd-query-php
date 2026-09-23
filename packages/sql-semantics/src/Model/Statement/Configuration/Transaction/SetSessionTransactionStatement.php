<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Transaction;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\Deferrability;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests ordered changes to PostgreSQL session transaction defaults.
 * @visibility public
 * @example Inspecting the transaction request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Transaction\SetSessionTransactionStatement // => true
 * @example Rejecting an empty mode request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SET TRANSACTION READ ONLY");
 *     new \SqlSemantics\Model\Statement\Configuration\Transaction\SetSessionTransactionStatement($statement->origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetSessionTransactionStatement extends ConfigurationStatement
{
    /**
     * @param non-empty-list<Isolation|Access|Deferrability> $modes Ordered mode changes, including repeated requests
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $modes, public readonly Locality $locality = Locality::Session)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SetSessionTransactionStatement requires PostgreSql.');
        }
        Collections::alternatives($modes, [Isolation::class, Access::class, Deferrability::class]);
        Collections::nonEmpty($modes);
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
        return new self($origin, $this->modes, $this->locality);
    }

    /**
     * Replaces modes in a new validated transaction request.
     * @param non-empty-list<Isolation|Access|Deferrability> $modes
     */
    public function withModes(array $modes): self
    {
        return $this->changed(new self($this->origin, $modes, $this->locality));
    }

    /**
     * Replaces locality in a new validated transaction request.
     */
    public function withLocality(Locality $locality): self
    {
        return $this->changed(new self($this->origin, $this->modes, $locality));
    }
}
