<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Type;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOptions;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Completes a base type with its input and output functions and its optional support functions and storage properties.
 * @visibility public
 * @example Creating a base type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE box3d (INPUT = box3d_in, OUTPUT = box3d_out, INTERNALLENGTH = VARIABLE, STORAGE = main)');
 *     $statement->options[2]->value // => -1
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE TYPE "box3d"(INPUT = "box3d_in", OUTPUT = "box3d_out", INTERNALLENGTH = -1, STORAGE = \'main\')'
 * @example Rejecting a base type without an output function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TYPE box3d (INPUT = box3d_in, OUTPUT = box3d_out)');
 *     $statement->withOptions([$statement->options[0]]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateBaseTypeStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly array $options)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        DefinitionOptions::validate(Collections::nonEmpty($options), BaseTypeAttribute::cases());
        DefinitionOptions::present($options);
        DefinitionOptions::require($options, [BaseTypeAttribute::Input, BaseTypeAttribute::Output]);
        $category = DefinitionOptions::value($options, BaseTypeAttribute::Category);
        if (is_string($category) && ($category === '' || ord($category[0]) < 32 || ord($category[0]) > 126)) {
            throw new InvalidStructure('A type category is one printable ASCII character.');
        }
        if (DefinitionOptions::find($options, BaseTypeAttribute::Element) !== null && DefinitionOptions::find($options, BaseTypeAttribute::Subscript) === null) {
            throw new InvalidStructure('An element type requires a subscripting function.');
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
     * Replaces the type attributes.
     * @param non-empty-list<DefinitionOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
