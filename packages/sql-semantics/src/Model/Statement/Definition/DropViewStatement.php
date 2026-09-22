<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * DropViewStatement requires the operands of this SQL operation.
 *
 * @visibility public
 */
final class DropViewStatement extends \SqlSemantics\Model\BoundStatement
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Relation\QualifiedName> Validated ordered operands
     */
    public readonly array $names;

    /**
     * @param list<\SqlSemantics\Model\Relation\QualifiedName> $names
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        array $names,
        public readonly bool $ifExists = false,
        public readonly \SqlSemantics\Model\Definition\DropBehavior $behavior = \SqlSemantics\Model\Definition\DropBehavior::Default,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\Collections::objects($names, \SqlSemantics\Model\Relation\QualifiedName::class);
        if ($names === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A DROP operation requires a named target.');
        }
        $this->names = \SqlSemantics\Model\Validation\Collections::nonEmpty($names);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->names, $this->ifExists, $this->behavior);
    }
}
