<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Enables or disables InnoDB redo logging (ALTER INSTANCE ENABLE|DISABLE INNODB REDO_LOG).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER INSTANCE DISABLE INNODB REDO_LOG');
 *     $statement->enabled // => false
 */
final class AlterRedoLogStatement extends BoundStatement
{
    /**
     * Records whether redo logging is requested on or off.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly bool $enabled)
    {
        ReplicationRelease::require($origin, 'ALTER INSTANCE INNODB REDO_LOG', 80000);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->enabled);
    }

    /**
     * Requests redo logging on or off.
     */
    public function withEnabled(bool $enabled): self
    {
        return $this->changed(new self($this->origin, $enabled));
    }
}
