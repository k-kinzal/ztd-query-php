<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * AddColumnStatement requires the operands of this SQL operation.
 *
 * @visibility public
 */
final class AddColumnStatement extends \SqlSemantics\Model\BoundStatement
{
    /**
     * @param list<\SqlSemantics\Schema\TableConstraint> $constraints
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $table,
        public readonly \SqlSemantics\Schema\ColumnDefinition $column,
        public readonly array $constraints = [],
        public readonly bool $ifNotExists = false,
    ) {
        parent::__construct($origin);
        if ($origin->dialect === \SqlSemantics\Dialect::Sqlite && array_filter($constraints, static fn ($constraint): bool => $constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey || $constraint instanceof \SqlSemantics\Schema\Constraint\UniqueKey) !== []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('SQLite ADD COLUMN cannot declare a PRIMARY KEY or UNIQUE constraint.');
        }
        \SqlSemantics\Model\Validation\Collections::objects($constraints, \SqlSemantics\Schema\TableConstraint::class);
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
        return new static($origin, $this->table, $this->column, $this->constraints, $this->ifNotExists);
    }
}
