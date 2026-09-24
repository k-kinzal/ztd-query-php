<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Locking;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Releases the instance-level backup lock held by the session.
 * @visibility public
 * @example Binding the backup lock release
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('UNLOCK INSTANCE');
 *     [$statement instanceof \SqlSemantics\Model\Statement\Locking\UnlockInstanceStatement, $statement->kind->value] // => [true, 'UNLOCK']
 */
final class UnlockInstanceStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        ReplicationRelease::require($origin, 'UNLOCK INSTANCE', 80000);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Unlock;
    }

    /**
     * Retains the release request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }
}
