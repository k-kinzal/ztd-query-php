<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Program;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\ProcedureParameter;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CREATE PROCEDURE: stores a named procedure, its parameters, characteristics and body without running it.
 * @visibility public
 * @example Inspecting a created procedure
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE DEFINER = CURRENT_USER PROCEDURE app.p(IN a INT) SQL SECURITY INVOKER SELECT a');
 *     $statement->name->parts // => ['app', 'p']
 *     $statement->definer === \SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated // => true
 *     $statement->toString() // => 'CREATE DEFINER = CURRENT_USER PROCEDURE `app`.`p`(IN `a` integer) SQL SECURITY INVOKER SELECT `a`'
 */
final class CreateProcedureStatement extends BoundStatement
{
    /**
     * Requires distinct parameter names and a body that matches the release and the procedure rules.
     * @param list<ProcedureParameter> $parameters
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly array $parameters,
        public readonly RoutineCharacteristics $characteristics,
        public readonly ProgramStatement|ExternalRoutineCode $body,
        public readonly AccountName|CurrentAccount|null $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        Collections::objects($parameters, ProcedureParameter::class);
        ProgramInvariant::definition($origin, $name, $ifNotExists, ProgramKind::Procedure);
        ProgramInvariant::distinct(array_map(static fn (ProcedureParameter $parameter): string => $parameter->name, $parameters));
        ProgramInvariant::body($origin, $body, ProgramKind::Procedure);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the procedure definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->parameters, $this->characteristics, $this->body, $this->definer, $this->ifNotExists);
    }

    /**
     * Replaces the procedure name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->parameters, $this->characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the ordered parameter list.
     * @param list<ProcedureParameter> $parameters
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->name, $parameters, $this->characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the declared characteristics.
     */
    public function withCharacteristics(RoutineCharacteristics $characteristics): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $characteristics, $this->body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the body; the result is revalidated against the parameters it references.
     */
    public function withBody(ProgramStatement|ExternalRoutineCode $body): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $this->characteristics, $body, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the definer account; null uses the account running the statement.
     */
    public function withDefiner(AccountName|CurrentAccount|null $definer): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->parameters, $this->characteristics, $this->body, $definer, $this->ifNotExists));
    }
}
