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
 * Reinitializes the keyring component from its configuration (ALTER INSTANCE RELOAD KEYRING).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER INSTANCE RELOAD KEYRING');
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\Administration\ReloadKeyringStatement // => true
 */
final class ReloadKeyringStatement extends BoundStatement
{
    /**
     * The request has no operands.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        ReplicationRelease::require($origin, 'ALTER INSTANCE RELOAD KEYRING', 80000);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
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
