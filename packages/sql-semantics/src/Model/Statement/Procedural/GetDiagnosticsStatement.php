<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\Model\Configuration\Condition\StatementDiagnostic;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Copies statement information items of a diagnostics area into user variables.
 * @visibility public
 * @example Reading the requested items
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GET STACKED DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT');
 *     [$statement->area->value, count($statement->items)] // => ['STACKED', 2]
 */
final class GetDiagnosticsStatement extends BoundStatement
{
    /**
     * @var non-empty-list<StatementDiagnostic>
     */
    public readonly array $items;

    /**
     * @param list<StatementDiagnostic> $items Items in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DiagnosticsArea $area, array $items)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('GET DIAGNOSTICS requires MySQL.');
        }
        Collections::objects($items, StatementDiagnostic::class);
        $this->items = Collections::nonEmpty($items);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::GetDiagnostics;
    }

    /**
     * Retains the area and items while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->area, $this->items);
    }

    /**
     * Reads another diagnostics area.
     */
    public function withArea(DiagnosticsArea $area): self
    {
        return $this->changed(new self($this->origin, $area, $this->items));
    }

    /**
     * Replaces the nonempty item list.
     * @param list<StatementDiagnostic> $items
     */
    public function withItems(array $items): self
    {
        return $this->changed(new self($this->origin, $this->area, $items));
    }
}
