<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPosition;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabels;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds a label to an enum type, at the end or next to an existing label.
 * @visibility public
 * @example Adding a label when it is missing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE IF NOT EXISTS 'calm' BEFORE 'happy'");
 *     $statement->label // => 'calm'
 *     $statement->ifNotExists // => true
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER TYPE "mood" ADD VALUE IF NOT EXISTS \'calm\' BEFORE \'happy\''
 */
final class AddEnumLabelStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $type, public readonly string $label, public readonly bool $ifNotExists = false, public readonly ?EnumLabelPosition $position = null)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($type);
        EnumLabels::label($label);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->type, $this->label, $this->ifNotExists, $this->position);
    }

    /**
     * Replaces the altered enum type.
     */
    public function withType(QualifiedName $type): self
    {
        return $this->changed(new self($this->origin, $type, $this->label, $this->ifNotExists, $this->position));
    }

    /**
     * Replaces the added label.
     */
    public function withLabel(string $label): self
    {
        return $this->changed(new self($this->origin, $this->type, $label, $this->ifNotExists, $this->position));
    }

    /**
     * Replaces the tolerance for an existing label.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->label, $ifNotExists, $this->position));
    }

    /**
     * Replaces or removes the position next to an existing label.
     */
    public function withPosition(?EnumLabelPosition $position): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->label, $this->ifNotExists, $position));
    }
}
