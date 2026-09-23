<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Transaction;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\DefaultScope;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Configures MySQL transaction defaults in an explicit scope.
 * @visibility public
 * @example Inspecting the transaction request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET SESSION TRANSACTION READ ONLY");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Transaction\SetDefaultTransactionStatement // => true
 */
final class SetDefaultTransactionStatement extends ConfigurationStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DefaultScope $scope, public readonly ?Isolation $isolation = null, public readonly ?Access $access = null)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('SetDefaultTransactionStatement requires MySql.');
        }
        if ($isolation === null && $access === null) {
            throw new InvalidStructure('Transaction configuration requires an isolation or access request.');
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
        return new self($origin, $this->scope, $this->isolation, $this->access);
    }

    /**
     * Replaces scope in a new validated transaction request.
     */
    public function withScope(DefaultScope $scope): self
    {
        return $this->changed(new self($this->origin, $scope, $this->isolation, $this->access));
    }

    /**
     * Replaces isolation in a new validated transaction request.
     */
    public function withIsolation(?Isolation $isolation): self
    {
        return $this->changed(new self($this->origin, $this->scope, $isolation, $this->access));
    }

    /**
     * Replaces access in a new validated transaction request.
     */
    public function withAccess(?Access $access): self
    {
        return $this->changed(new self($this->origin, $this->scope, $this->isolation, $access));
    }
}
