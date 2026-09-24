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
 * Lists the events of one relay log file of a replication channel from a start position.
 * @visibility public
 * @example Inspecting the log and channel
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW RELAYLOG EVENTS IN 'relay.000004' FOR CHANNEL 'east'");
 *     [$statement->log, $statement->position, $statement->channel] // => ['relay.000004', null, 'east']
 */
final class ShowRelayLogEventsStatement extends InspectionStatement
{
    /**
     * @param string|null $log Log file name; null reads the first relay log
     * @param Literal|null $position Start position as a numeric literal; null starts at the first event
     * @param RowWindow|null $limit Row window; null returns every event
     * @param string|null $channel Replication channel; null selects the default channel
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?string $log = null, public readonly ?Literal $position = null, public readonly ?RowWindow $limit = null, public readonly ?string $channel = null)
    {
        LogEventPosition::check($position);
        ReplicationChannel::check($origin, $channel);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->log, $this->position, $this->limit, $this->channel);
    }

    /**
     * Reads another log file; null reads the first relay log.
     */
    public function withLog(?string $log): self
    {
        return $this->changed(new self($this->origin, $log, $this->position, $this->limit, $this->channel));
    }

    /**
     * Starts at another position; null starts at the first event.
     */
    public function withPosition(?Literal $position): self
    {
        return $this->changed(new self($this->origin, $this->log, $position, $this->limit, $this->channel));
    }

    /**
     * Replaces the row window; null returns every event.
     */
    public function withLimit(?RowWindow $limit): self
    {
        return $this->changed(new self($this->origin, $this->log, $this->position, $limit, $this->channel));
    }

    /**
     * Reads the relay log of another channel; null selects the default channel.
     */
    public function withChannel(?string $channel): self
    {
        return $this->changed(new self($this->origin, $this->log, $this->position, $this->limit, $channel));
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
