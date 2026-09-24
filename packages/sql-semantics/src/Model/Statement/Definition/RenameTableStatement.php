<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * RenameTableStatement requires the operands of this SQL operation.
 *
 * @visibility public
 * @example Renaming a table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t RENAME TO u');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\RenameTableStatement // => true
 *     $statement->newName // => 'u'
 */
final class RenameTableStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $table,
        public readonly string $newName,
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
        return new static($origin, $this->table, $this->newName);
    }
}
