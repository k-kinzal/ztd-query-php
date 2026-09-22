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
 * Requests a database checkpoint.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CHECKPOINT');
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\CheckpointStatement // => true
 * @visibility public
 */
final class CheckpointStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('CheckpointStatement requires PostgreSql.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Checkpoint;
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
