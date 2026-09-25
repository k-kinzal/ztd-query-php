<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds, drops, and retypes attributes of a composite type in one command.
 * @visibility public
 * @example Changing composite attributes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair ADD ATTRIBUTE note text, DROP ATTRIBUTE IF EXISTS amount CASCADE');
 *     count($statement->changes) // => 2
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'ALTER TYPE "pair" ADD ATTRIBUTE "note" text, DROP ATTRIBUTE IF EXISTS "amount" CASCADE'
 * @example Rejecting an empty change list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair DROP ATTRIBUTE a');
 *     $statement->withChanges([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterCompositeTypeStatement extends BoundStatement
{
    /**
     * @param non-empty-list<Composite\AttributeChange> $changes
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $type, public readonly array $changes)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($type);
        Collections::objects(Collections::nonEmpty($changes), Composite\AttributeChange::class);
        $added = array_map(static fn (Composite\AddAttribute $change): string => $change->attribute->name, array_values(array_filter($changes, static fn (Composite\AttributeChange $change): bool => $change instanceof Composite\AddAttribute)));
        if (count(array_unique($added)) !== count($added)) {
            throw new InvalidStructure('A composite type change adds each attribute name once.');
        }
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
        return new self($origin, $this->type, $this->changes);
    }

    /**
     * Replaces the altered composite type.
     */
    public function withType(QualifiedName $type): self
    {
        return $this->changed(new self($this->origin, $type, $this->changes));
    }

    /**
     * Replaces the attribute changes.
     * @param non-empty-list<Composite\AttributeChange> $changes
     */
    public function withChanges(array $changes): self
    {
        return $this->changed(new self($this->origin, $this->type, $changes));
    }
}
