<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * DropColumnStatement requires the operands of this SQL operation.
 *
 * @visibility public
 * @example Dropping a column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DROP COLUMN n');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\DropColumnStatement // => true
 *     $statement->column // => 'n'
 */
final class DropColumnStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $table,
        public readonly string $column,
        public readonly bool $ifExists = false,
        public readonly \SqlSemantics\Model\Definition\DropBehavior $behavior = \SqlSemantics\Model\Definition\DropBehavior::Default,
    ) {
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->table, $this->column, $this->ifExists, $this->behavior);
    }
}
