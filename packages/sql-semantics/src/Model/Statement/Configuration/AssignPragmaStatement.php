<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * AssignPragmaStatement requires the operands of this SQL operation.
 *
 * @visibility public
 */
final class AssignPragmaStatement extends \SqlSemantics\Model\Statement\ConfigurationStatement
{
    /**

     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $name,
        public readonly \SqlSemantics\Model\Configuration\Pragma\Argument $value,
    ) {
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Pragma;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->value);
    }

    /**
     * Replaces the argument while preserving the pragma's scalar-value grammar.
     */
    public function withValue(\SqlSemantics\Model\Configuration\Pragma\Argument $value): self
    {
        return $this->changed(new self($this->origin, $this->name, $value));
    }
}
