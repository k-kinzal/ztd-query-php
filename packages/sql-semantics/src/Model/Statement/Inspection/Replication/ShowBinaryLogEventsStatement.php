<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Replication\LogEventField;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the events of one binary log file from a start position.
 * @visibility public
 * @example Inspecting the log, position, and window
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW BINLOG EVENTS IN 'binlog.000002' FROM 157 LIMIT 5");
 *     [$statement->log, $statement->position?->text, $statement->limit?->count->spelling()] // => ['binlog.000002', '157', '5']
 */
final class ShowBinaryLogEventsStatement extends InspectionStatement
{
    /**
     * @param string|null $log Log file name; null reads the first binary log
     * @param Literal|null $position Start position as a numeric literal; null starts at the first event
     * @param RowWindow|null $limit Row window; null returns every event
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?string $log = null, public readonly ?Literal $position = null, public readonly ?RowWindow $limit = null)
    {
        LogEventPosition::check($position);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->log, $this->position, $this->limit);
    }

    /**
     * Reads another log file; null reads the first binary log.
     */
    public function withLog(?string $log): self
    {
        return $this->changed(new self($this->origin, $log, $this->position, $this->limit));
    }

    /**
     * Starts at another position; null starts at the first event.
     */
    public function withPosition(?Literal $position): self
    {
        return $this->changed(new self($this->origin, $this->log, $position, $this->limit));
    }

    /**
     * Replaces the row window; null returns every event.
     */
    public function withLimit(?RowWindow $limit): self
    {
        return $this->changed(new self($this->origin, $this->log, $this->position, $limit));
    }

    /**
     * @return list<OutputColumn> The log, position, event, server, and description fields
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(LogEventField::cases());
    }
}
