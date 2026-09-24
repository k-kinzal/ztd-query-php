<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Stops group replication on this member.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('STOP GROUP_REPLICATION');
 *     $statement->kind->value // => 'STOP'
 */
final class StopGroupReplicationStatement extends BoundStatement
{
    /**
     * The request has no operands.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        ReplicationRelease::require($origin, 'STOP GROUP_REPLICATION', 50700);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Stop;
    }

    /**
     * Changes only diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }
}
