<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Server;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Engine\EngineReport;
use SqlSemantics\Model\Query\Inspection\Engine\EngineSelection;
use SqlSemantics\Model\Query\Inspection\Field\Session\EngineReportField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests a status, mutex, or log report from one storage engine or from every engine.
 * @visibility public
 * @example Inspecting the requested report
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW ENGINE INNODB STATUS');
 *     [$statement->engine, $statement->report->value] // => ['INNODB', 'STATUS']
 */
final class ShowEngineReportStatement extends InspectionStatement
{
    /**
     * @param string|EngineSelection $engine Storage engine name as written, or every engine
     * @param EngineReport $report Report the engine is asked to produce
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string|EngineSelection $engine, public readonly EngineReport $report)
    {
        if ($engine === '') {
            throw new InvalidStructure('An engine name requires at least one character.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->engine, $this->report);
    }

    /**
     * Asks another engine, or every engine, for the same report.
     */
    public function withEngine(string|EngineSelection $engine): self
    {
        return $this->changed(new self($this->origin, $engine, $this->report));
    }

    /**
     * Asks the same engine for another report.
     */
    public function withReport(EngineReport $report): self
    {
        return $this->changed(new self($this->origin, $this->engine, $report));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(EngineReportField::cases());
    }
}
