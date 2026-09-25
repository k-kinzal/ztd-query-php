<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Composite\CompositeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a composite type from an ordered list of uniquely named attributes, which may be empty.
 * @visibility public
 * @example Creating a composite type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (label text, amount integer)');
 *     count($statement->attributes) // => 2
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TYPE "pair" AS ("label" text, "amount" integer)'
 * @example Rejecting a repeated attribute name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (a text)');
 *     $statement->withAttributes([$statement->attributes[0], $statement->attributes[0]]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateCompositeTypeStatement extends BoundStatement
{
    /**
     * @param list<CompositeAttribute> $attributes
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly array $attributes)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        Collections::objects($attributes, CompositeAttribute::class);
        $names = array_map(static fn (CompositeAttribute $attribute): string => $attribute->name, $attributes);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('A composite type declares each attribute name once.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->attributes);
    }

    /**
     * Replaces the type name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->attributes));
    }

    /**
     * Replaces the attributes.
     * @param list<CompositeAttribute> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return $this->changed(new self($this->origin, $this->name, $attributes));
    }
}
