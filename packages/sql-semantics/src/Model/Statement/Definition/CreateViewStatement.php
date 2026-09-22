<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * CreateViewStatement requires the operands of this SQL operation.
 *
 * @visibility public
 */
final class CreateViewStatement extends \SqlSemantics\Model\BoundStatement
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
        public readonly bool $temporary = false,
        public readonly bool $replace = false,
        public readonly bool $ifNotExists = false,
        public readonly \SqlSemantics\Model\Definition\ViewCheck $check = \SqlSemantics\Model\Definition\ViewCheck::None,
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
        return new static($origin, $this->name, $this->query, $this->columns, $this->temporary, $this->replace, $this->ifNotExists, $this->check);
    }
}
