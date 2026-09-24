<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Program;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\FunctionParameter;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CREATE FUNCTION: stores a named stored function, its parameters, return domain, characteristics and body without running it.
 * @visibility public
 * @example Inspecting a created function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE FUNCTION twice(a INT) RETURNS BIGINT DETERMINISTIC RETURN a * 2');
 *     $statement->returns->type->name // => 'bigint'
 *     $statement->characteristics->deterministic // => true
 *     $statement->toString() // => 'CREATE FUNCTION `twice`(`a` integer) RETURNS bigint DETERMINISTIC RETURN(`a` * 2)'
 */
final class CreateFunctionStatement extends BoundStatement
{
    /**
     * Requires distinct parameter names and a body that matches the release and the function rules, including a RETURN.
     * @param list<FunctionParameter> $parameters
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly array $parameters,
        public readonly DeclaredDomain $returns,
        public readonly RoutineCharacteristics $characteristics,
        public readonly ProgramStatement|ExternalRoutineCode $body,
        public readonly AccountName|CurrentAccount|null $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        Collections::objects($parameters, FunctionParameter::class);
        ProgramInvariant::definition($origin, $name, $ifNotExists, ProgramKind::Function);
        ProgramInvariant::distinct(array_map(static fn (FunctionParameter $parameter): string => $parameter->name, $parameters));
        ProgramInvariant::body($origin, $body, ProgramKind::Function);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the function definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->parameters, $this->returns, $this->characteristics, $this->body, $this->definer, $this->ifNotExists);
    }

    /**
     * Replaces the function name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->parameters, $this->returns, $this->characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the ordered parameter list.
     * @param list<FunctionParameter> $parameters
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->name, $parameters, $this->returns, $this->characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the declared return domain.
     */
    public function withReturns(DeclaredDomain $returns): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $returns, $this->characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the declared characteristics.
     */
    public function withCharacteristics(RoutineCharacteristics $characteristics): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $this->returns, $characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the body; the result is revalidated against the parameters it references.
     */
    public function withBody(ProgramStatement|ExternalRoutineCode $body): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $this->returns, $this->characteristics, $body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the definer account; null uses the account running the statement.
     */
    public function withDefiner(AccountName|CurrentAccount|null $definer): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $this->returns, $this->characteristics, $this->body, $definer, $this->ifNotExists));
    }
}
