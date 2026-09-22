<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * CreateTableAsStatement requires the operands of this SQL operation.
 *
 * @visibility public
  * @example Inspecting CreateTableAsStatement
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('CREATE TABLE t AS SELECT 1 AS id');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\CreateTableAsStatement // => true
 */
final class CreateTableAsStatement extends \SqlSemantics\Model\BoundStatement
{
    /**
     * @param list<string> $columns
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $name,
        public readonly \SqlSemantics\Model\BoundQuery $query,
        public readonly array $columns = [],
        public readonly ?\SqlSemantics\Schema\Table\Properties $properties = null,
        public readonly bool $withData = true,
        public readonly bool $ifNotExists = false,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\Collections::strings($columns);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->query, $this->columns, $this->properties, $this->withData, $this->ifNotExists);
    }
}
