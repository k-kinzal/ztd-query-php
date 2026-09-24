<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineCodeField;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the compiled instructions of a stored function or stored procedure.
 * @visibility public
 * @example Inspecting the named routine
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROCEDURE CODE app.sync');
 *     [$statement->routine->value, $statement->name->parts] // => ['PROCEDURE', ['app', 'sync']]
 */
final class ShowRoutineCodeStatement extends InspectionStatement
{
    /**
     * @param RoutineKind $routine Whether a function or a procedure is listed
     * @param QualifiedName $name Optionally database-qualified routine name
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RoutineKind $routine, public readonly QualifiedName $name)
    {
        if (count($name->parts) > 2) {
            throw new InvalidStructure('A routine name takes at most a database qualifier.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->routine, $this->name);
    }

    /**
     * Lists the other routine kind under the same name.
     */
    public function withRoutine(RoutineKind $routine): self
    {
        return $this->changed(new self($this->origin, $routine, $this->name));
    }

    /**
     * Names another routine.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->routine, $name));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(RoutineCodeField::cases());
    }
}
