<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOptions;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the estimators, partners, or capabilities of an existing operator; a null estimator or partner is NONE.
 * @visibility public
 * @example Removing an estimator and enabling hashing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR === (integer, integer) SET (RESTRICT = NONE, HASHES)');
 *     $statement->options[0]->value // => null
 *     $statement->toString() // => 'ALTER OPERATOR === (integer, integer) SET (RESTRICT = NONE, HASHES = TRUE)'
 * @example Rejecting a change of the implementing function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR === (integer, integer) SET (HASHES)');
 *     $statement->withOptions([new \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption(\SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute::Function, new \SqlSemantics\Model\Relation\QualifiedName(['f']))]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterOperatorStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly OperatorIdentity $operator, public readonly array $options)
    {
        TypeSystemInvariant::dialect($origin);
        DefinitionOptions::validate(Collections::nonEmpty($options), array_values(array_filter(OperatorAttribute::cases(), static fn (OperatorAttribute $attribute): bool => $attribute->alterable())));
        foreach ($options as $option) {
            if ($option->value === null && $option->attribute->kind() === DefinitionKind::Boolean) {
                throw new InvalidStructure('A capability is set to TRUE or FALSE.');
            }
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
        return new self($origin, $this->operator, $this->options);
    }

    /**
     * Replaces the altered operator.
     */
    public function withOperator(OperatorIdentity $operator): self
    {
        return $this->changed(new self($this->origin, $operator, $this->options));
    }

    /**
     * Replaces the changed attributes.
     * @param non-empty-list<DefinitionOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->operator, $options));
    }
}
