<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Releases explicitly acquired table locks.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UNLOCK TABLES');
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\UnlockTablesStatement // => true
 * @visibility public
 */
final class UnlockTablesStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('UnlockTablesStatement requires MySql.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Unlock;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin);
    }
}
