<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Routine;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Catalog\Kind\RoutineKind;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Option\OptionInvariant;
use SqlSemantics\Model\Definition\Routine\Option\RoutineOption;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes attributes of an existing PostgreSQL function, procedure, or routine; RESTRICT is noise.
 * A procedure accepts only SECURITY, SET, and RESET; for ROUTINE the server applies the rules of the object it finds.
 * @visibility public
 * @example Reading the changed attributes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION app.f(integer) STABLE PARALLEL SAFE RESET ALL RESTRICT');
 *     $statement->routine // => \SqlSemantics\Model\Definition\Catalog\Kind\RoutineKind::Function
 *     $statement->target->name->parts // => ['app', 'f']
 *     $statement->changes[1] // => \SqlSemantics\Model\Definition\Routine\Option\ParallelSafety::Safe
 * @example Rejecting a function attribute of a procedure
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURE p SECURITY INVOKER');
 *     $statement->withChanges([\SqlSemantics\Model\Definition\Routine\Option\Volatility::Volatile]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterRoutineStatement extends BoundStatement
{
    /**
     * @param RoutineKind $routine Object class named by the request (FUNCTION, PROCEDURE, ROUTINE)
     * @param non-empty-list<RoutineOption|RoutineSecurity> $changes Changed attributes in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RoutineKind $routine, public readonly RoutineByName|RoutineBySignature $target, public readonly array $changes)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Routine alterations with these attributes require PostgreSQL.');
        }
        CatalogInvariant::name($target->name, 3);
        OptionInvariant::options(Collections::nonEmpty($changes), $routine === RoutineKind::Procedure);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the attribute changes while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->routine, $this->target, $this->changes);
    }

    /**
     * Replaces the object class named by the request.
     */
    public function withRoutine(RoutineKind $routine): self
    {
        return $this->changed(new self($this->origin, $routine, $this->target, $this->changes));
    }

    /**
     * Replaces the altered routine.
     */
    public function withTarget(RoutineByName|RoutineBySignature $target): self
    {
        return $this->changed(new self($this->origin, $this->routine, $target, $this->changes));
    }

    /**
     * Replaces the changed attributes.
     * @param non-empty-list<RoutineOption|RoutineSecurity> $changes
     */
    public function withChanges(array $changes): self
    {
        return $this->changed(new self($this->origin, $this->routine, $this->target, $changes));
    }
}
