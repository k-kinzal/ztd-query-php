<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Terminates the connection identified by an expression.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('KILL CONNECTION 42');
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\KillConnectionStatement // => true
 * @visibility public
 */
final class KillConnectionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Expression $connectionId)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('KillConnectionStatement requires MySql.');
        }
        if (($connectionId->type->dialect !== $origin->dialect)) {
            throw new InvalidStructure('connectionId must retain the statement dialect.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Kill;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->connectionId);
    }
}
