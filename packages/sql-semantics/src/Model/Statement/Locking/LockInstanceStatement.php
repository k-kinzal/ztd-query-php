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
 * Takes the instance-level backup lock, which blocks file-changing DDL while DML continues.
 * @visibility public
 * @example Binding the backup lock
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('LOCK INSTANCE FOR BACKUP');
 *     [$statement instanceof \SqlSemantics\Model\Statement\Locking\LockInstanceStatement, $statement->kind->value] // => [true, 'LOCK']
 */
final class LockInstanceStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        ReplicationRelease::require($origin, 'LOCK INSTANCE FOR BACKUP', 80000);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Lock;
    }

    /**
     * Retains the lock request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }
}
