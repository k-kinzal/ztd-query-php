<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Program;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Stored\EventCompletion;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Definition\Routine\Stored\OneTimeSchedule;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\RecurringSchedule;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CREATE EVENT: a named program the event scheduler runs once or repeatedly; omitted options are ENABLE and NOT PRESERVE.
 * @visibility public
 * @example Inspecting a scheduled event
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE EVENT IF NOT EXISTS nightly_purge ON SCHEDULE EVERY 1 DAY ON COMPLETION PRESERVE COMMENT 'nightly' DO DO SLEEP(1)");
 *     [$statement->ifNotExists, $statement->completion->value, $statement->status->value] // => [true, 'ON COMPLETION PRESERVE', 'ENABLE']
 */
final class CreateEventStatement extends BoundStatement
{
    /**
     * Requires a MySQL comment literal and a body without RETURN.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly OneTimeSchedule|RecurringSchedule $schedule,
        public readonly ProgramStatement $body,
        public readonly EventCompletion $completion = EventCompletion::Drop,
        public readonly EventStatus $status = EventStatus::Enabled,
        public readonly ?Literal $comment = null,
        public readonly AccountName|CurrentAccount|null $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        ProgramInvariant::definition($origin, $name, $ifNotExists, ProgramKind::Event);
        if ($comment !== null && ($comment->type->dialect !== Dialect::MySql || $comment->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('An event comment requires a MySQL text literal.');
        }
        ProgramInvariant::body($origin, $body, ProgramKind::Event);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the event definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->schedule, $this->body, $this->completion, $this->status, $this->comment, $this->definer, $this->ifNotExists);
    }

    /**
     * Replaces the event name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->schedule, $this->body, $this->completion, $this->status, $this->comment, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the schedule.
     */
    public function withSchedule(OneTimeSchedule|RecurringSchedule $schedule): self
    {
        return $this->changed(new self($this->origin, $this->name, $schedule, $this->body, $this->completion, $this->status, $this->comment, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the body.
     */
    public function withBody(ProgramStatement $body): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->schedule, $body, $this->completion, $this->status, $this->comment, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the status.
     */
    public function withStatus(EventStatus $status): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->schedule, $this->body, $this->completion, $status, $this->comment, $this->definer, $this->ifNotExists));
    }
}
