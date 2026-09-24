<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabels;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames one label of an enum type to a different label.
 * @visibility public
 * @example Renaming a label
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
 *     $statement->label // => 'ok'
 *     $statement->newLabel // => 'fine'
 * @example Rejecting a rename to the same label
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
 *     $statement->withNewLabel('ok'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameEnumLabelStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $type, public readonly string $label, public readonly string $newLabel)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($type);
        EnumLabels::labels([$label, $newLabel]);
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
        return new self($origin, $this->type, $this->label, $this->newLabel);
    }

    /**
     * Replaces the altered enum type.
     */
    public function withType(QualifiedName $type): self
    {
        return $this->changed(new self($this->origin, $type, $this->label, $this->newLabel));
    }

    /**
     * Replaces the renamed label.
     */
    public function withLabel(string $label): self
    {
        return $this->changed(new self($this->origin, $this->type, $label, $this->newLabel));
    }

    /**
     * Replaces the new label.
     */
    public function withNewLabel(string $newLabel): self
    {
        return $this->changed(new self($this->origin, $this->type, $this->label, $newLabel));
    }
}
