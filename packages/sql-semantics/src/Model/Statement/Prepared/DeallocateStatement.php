<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Prepared;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Deallocates exactly one named prepared statement.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DEALLOCATE PREPARE s', strict: false);
 *     $statement->toString() // => 'DEALLOCATE PREPARE `s`'
 *
 * @visibility public
 */
final class DeallocateStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name)
    {
        if ($origin->dialect === Dialect::Sqlite) {
            throw new InvalidStructure('SQLite has no SQL deallocation statement.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Deallocate;
    }

    /**
     * Retains the prepared-statement operands when replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name);
    }
}
