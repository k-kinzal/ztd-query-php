<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Module\Invocation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a SQLite virtual table by invoking a named module constructor.
 * @visibility public
  * @example Inspecting CreateVirtualTableStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind('CREATE VIRTUAL TABLE temp.docs USING fts5(title, body, tokenize="porter ascii")');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement // => true
 */
final class CreateVirtualTableStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly Invocation $constructor, public readonly bool $ifNotExists = false)
    {
        parent::__construct($origin);
        if ($origin->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('Virtual-table module constructors belong to SQLite.');
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->constructor, $this->ifNotExists);
    }

    /**
     * Replaces the module constructor while preserving the table declaration form.
     */
    public function withConstructor(Invocation $constructor): self
    {
        return $this->changed(new self($this->origin, $this->name, $constructor, $this->ifNotExists));
    }
}
