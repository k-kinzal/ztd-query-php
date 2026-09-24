<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Routine;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration;
use SqlSemantics\Model\Definition\Routine\Declaration\RoutineImplementation;
use SqlSemantics\Model\Definition\Routine\Option\RoutineOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates or replaces a procedure, which CALL invokes and which may manage transactions.
 * @visibility public
 * @example Reading the procedure definition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE app.tidy(INOUT n integer) LANGUAGE plpgsql SECURITY DEFINER SET search_path = app AS 'BEGIN n := 0; END'");
 *     $statement->name->parts // => ['app', 'tidy']
 *     $statement->options[0] // => \SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity::Definer
 * @example Rejecting a function attribute
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE PROCEDURE p() LANGUAGE sql AS 'SELECT 1'");
 *     $statement->withOptions([\SqlSemantics\Model\Definition\Routine\Option\Volatility::Stable]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateProcedureStatement extends BoundStatement
{
    /**
     * @param bool $orReplace Whether an existing procedure with this signature is replaced (OR REPLACE)
     * @param list<ParameterDeclaration> $parameters
     * @param list<RoutineOption|RoutineSecurity> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly bool $orReplace, public readonly QualifiedName $name, public readonly array $parameters, public readonly RoutineImplementation $implementation, public readonly array $options)
    {
        RoutineInvariant::definition($origin, $name, $parameters, $options, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->orReplace, $this->name, $this->parameters, $this->implementation, $this->options);
    }

    /**
     * Replaces whether an existing routine with this signature is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $orReplace, $this->name, $this->parameters, $this->implementation, $this->options));
    }
    /**
     * Replaces the routine name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $name, $this->parameters, $this->implementation, $this->options));
    }
    /**
     * Replaces the parameter declarations.
     * @param list<ParameterDeclaration> $parameters
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $parameters, $this->implementation, $this->options));
    }
    /**
     * Replaces the language and body.
     */
    public function withImplementation(RoutineImplementation $implementation): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $implementation, $this->options));
    }
    /**
     * Replaces the attributes.
     * @param list<RoutineOption|RoutineSecurity> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->implementation, $options));
    }
}
