<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOptions;
use SqlSemantics\Model\Definition\TypeSystem\Definition\RangeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a range type over a subtype, with its optional operator class, collation, canonical and difference functions, and multirange name.
 * @visibility public
 * @example Creating a range type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE floatrange AS RANGE (SUBTYPE = float8, SUBTYPE_DIFF = float8mi)');
 *     $statement->options[0]->value->name // => 'double precision'
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TYPE "floatrange" AS RANGE(SUBTYPE = double precision, SUBTYPE_DIFF = "float8mi")'
 */
final class CreateRangeTypeStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly array $options)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        DefinitionOptions::validate(Collections::nonEmpty($options), RangeAttribute::cases());
        DefinitionOptions::present($options);
        DefinitionOptions::require($options, [RangeAttribute::Subtype]);
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
        return new self($origin, $this->name, $this->options);
    }

    /**
     * Replaces the type name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the range attributes.
     * @param non-empty-list<DefinitionOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
