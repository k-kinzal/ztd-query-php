<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Administration\MasterKeyScope;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces the InnoDB or binary log encryption master key (ALTER INSTANCE ROTATE ... MASTER KEY).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER INSTANCE ROTATE BINLOG MASTER KEY');
 *     $statement->scope->value // => 'BINLOG'
 */
final class RotateMasterKeyStatement extends BoundStatement
{
    /**
     * Binary log keys rotate from MySQL 8.0; InnoDB keys from 5.7.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly MasterKeyScope $scope)
    {
        ReplicationRelease::require($origin, 'ALTER INSTANCE ROTATE ' . $scope->value . ' MASTER KEY', $scope === MasterKeyScope::InnoDb ? 50700 : 80000);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the key scope when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->scope);
    }

    /**
     * Selects the other key consumer.
     */
    public function withScope(MasterKeyScope $scope): self
    {
        return $this->changed(new self($this->origin, $scope));
    }
}
