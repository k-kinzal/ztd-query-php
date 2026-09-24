<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Session;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\DiagnosticCountField;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Counts the conditions the previous statement left in the diagnostics area, without clearing them.
 * @visibility public
 * @example Inspecting a count request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW COUNT(*) WARNINGS');
 *     [$statement->selection->value, $statement->resultColumns()[0]->name] // => ['WARNINGS', '@@session.warning_count']
 */
final class ShowDiagnosticCountStatement extends InspectionStatement
{
    /**
     * @param DiagnosticSelection $selection Every condition, or errors only
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DiagnosticSelection $selection)
    {
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->selection);
    }

    /**
     * Counts another selection of conditions.
     */
    public function withSelection(DiagnosticSelection $selection): self
    {
        return $this->changed(new self($this->origin, $selection));
    }

    /**
     * @return list<OutputColumn> The session counter of the selected conditions
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns([DiagnosticCountField::of($this->selection)]);
    }
}
