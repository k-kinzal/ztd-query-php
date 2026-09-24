<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Session;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\DiagnosticField;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the conditions the previous statement left in the diagnostics area, without clearing them.
 * @visibility public
 * @example Inspecting a windowed warnings request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW WARNINGS LIMIT 10');
 *     [$statement->selection->value, $statement->limit?->count->spelling(), array_column($statement->resultColumns(), 'name')] // => ['WARNINGS', '10', ['Level', 'Code', 'Message']]
 */
final class ShowDiagnosticsStatement extends InspectionStatement
{
    /**
     * @param DiagnosticSelection $selection Every condition, or errors only
     * @param RowWindow|null $limit Row window; null returns every selected condition
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DiagnosticSelection $selection, public readonly ?RowWindow $limit = null)
    {
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->selection, $this->limit);
    }

    /**
     * Reports another selection of conditions.
     */
    public function withSelection(DiagnosticSelection $selection): self
    {
        return $this->changed(new self($this->origin, $selection, $this->limit));
    }

    /**
     * Replaces the row window; null returns every selected condition.
     */
    public function withLimit(?RowWindow $limit): self
    {
        return $this->changed(new self($this->origin, $this->selection, $limit));
    }

    /**
     * @return list<OutputColumn> The level, code, and message fields
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(DiagnosticField::cases());
    }
}
