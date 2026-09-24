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
 * Changes the optional support functions or the storage strategy of a base type; a null function is NONE.
 * @visibility public
 * @example Removing a receive function and changing the storage
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE app.box3d SET (RECEIVE = NONE, STORAGE = extended)');
 *     $statement->options[0]->value // => null
 *     $statement->toString() // => 'ALTER TYPE "app"."box3d" SET (RECEIVE = NONE, STORAGE = \'extended\')'
 * @example Rejecting a change of the input function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE box3d SET (SEND = NONE)');
 *     $statement->withOptions([new \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption(\SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute::Input, null)]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterTypeOptionsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $type, public readonly array $options)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($type);
        DefinitionOptions::validate(Collections::nonEmpty($options), array_values(array_filter(BaseTypeAttribute::cases(), static fn (BaseTypeAttribute $attribute): bool => $attribute->alterable())));
        if (DefinitionOptions::find($options, BaseTypeAttribute::Storage)?->value === null && DefinitionOptions::find($options, BaseTypeAttribute::Storage) !== null) {
            throw new InvalidStructure('A storage change names the storage strategy.');
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
        return new self($origin, $this->type, $this->options);
    }

    /**
     * Replaces the altered type.
     */
    public function withType(QualifiedName $type): self
    {
        return $this->changed(new self($this->origin, $type, $this->options));
    }

    /**
     * Replaces the changed attributes.
     * @param non-empty-list<DefinitionOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->type, $options));
    }
}
