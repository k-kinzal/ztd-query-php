<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Operator;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOptions;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a prefix or binary operator implemented by a function, with its optional partners, estimators, and capabilities.
 * @visibility public
 * @example Creating a binary operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR app.=== (PROCEDURE = int4eq, LEFTARG = integer, RIGHTARG = integer, COMMUTATOR = ===, SORT1 = <)');
 *     $statement->name->parts // => ['app', '===']
 *     $statement->options[0]->attribute // => \SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute::Function
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE OPERATOR "app".=== (FUNCTION = "int4eq", LEFTARG = integer, RIGHTARG = integer, COMMUTATOR = ===, MERGES = TRUE)'
 * @example Rejecting an operator without a right operand
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (FUNCTION = f, RIGHTARG = integer)');
 *     $statement->withOptions([$statement->options[0]]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateOperatorStatement extends BoundStatement
{
    /**
     * @param non-empty-list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly array $options)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        if (preg_match(DefinitionKind::SYMBOL, $name->parts[count($name->parts) - 1]) !== 1) {
            throw new InvalidStructure('An operator name ends in an operator symbol.');
        }
        DefinitionOptions::validate(Collections::nonEmpty($options), OperatorAttribute::cases());
        DefinitionOptions::present($options);
        DefinitionOptions::require($options, [OperatorAttribute::Function, OperatorAttribute::RightArg]);
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
     * Replaces the operator name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->options));
    }

    /**
     * Replaces the operator attributes.
     * @param non-empty-list<DefinitionOption> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $options));
    }
}
