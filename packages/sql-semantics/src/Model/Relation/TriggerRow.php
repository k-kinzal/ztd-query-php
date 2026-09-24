<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Schema\TableDefinition;

/**
 * The OLD or NEW row image supplied by a trigger's subject table.
 * @visibility public
 * @example Naming a row image after its version
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
 *     $subject = $statement->subject;
 *     $row = new \SqlSemantics\Model\Relation\TriggerRow('r9', $subject->scopeId, $subject->declaration, $subject->source, \SqlSemantics\Model\Trigger\RowVersion::Old);
 *     $row->alias // => 'old'
 *     $row->declaration === $subject->declaration // => true
 *     $row->resultExpressions() // => []
 */
final class TriggerRow extends TableUse
{
    /**
     * @visibility SqlSemantics
     */
    public function __construct(string $id, string $scopeId, TableDefinition $declaration, Node $source, public readonly RowVersion $version)
    {
        parent::__construct($id, $scopeId, $declaration, $version->value, $source);
    }

    /**
     * Returns the ordered value expressions exposed by this relation.
     * @return list<\SqlSemantics\Model\Expression>
     */
    #[Override]
    public function resultExpressions(): array
    {
        return [];
    }

    /**
     * Returns a new relation occurrence in the supplied scope, retaining its source and operands.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->source, $this->version);
    }
}
