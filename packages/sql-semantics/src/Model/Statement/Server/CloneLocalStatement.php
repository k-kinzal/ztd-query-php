<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Clones the local database into the specified directory.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("CLONE LOCAL DATA DIRECTORY '/tmp/clone'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\CloneLocalStatement // => true
 * @visibility public
 */
final class CloneLocalStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Literal $directory)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('CloneLocalStatement requires MySql.');
        }
        if (($directory->type->dialect !== $origin->dialect || $directory->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('directory must retain the statement dialect and be a text literal.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Clone;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->directory);
    }
}
