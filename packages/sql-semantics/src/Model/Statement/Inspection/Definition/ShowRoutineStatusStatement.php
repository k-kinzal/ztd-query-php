<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\RoutineStatusField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists stored functions or stored procedures with their characteristics.
 * @visibility public
 * @example Inspecting a filtered listing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW FUNCTION STATUS LIKE 'calc%'");
 *     [$statement->routine->value, $statement->filter->pattern->text] // => ['FUNCTION', "'calc%'"]
 */
final class ShowRoutineStatusStatement extends InspectionStatement
{
    /**
     * @param RoutineKind $routine Whether functions or procedures are listed
     * @param PatternFilter|ConditionFilter|null $filter Restriction on the listed routines; null lists every routine
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RoutineKind $routine, public readonly PatternFilter|ConditionFilter|null $filter = null)
    {
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->routine, $this->filter);
    }

    /**
     * Lists the other routine kind.
     */
    public function withRoutine(RoutineKind $routine): self
    {
        return $this->changed(new self($this->origin, $routine, $this->filter));
    }

    /**
     * Replaces the restriction; null lists every routine.
     */
    public function withFilter(PatternFilter|ConditionFilter|null $filter): self
    {
        return $this->changed(new self($this->origin, $this->routine, $filter));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(RoutineStatusField::cases());
    }
}
