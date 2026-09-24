<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateEventField;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE statement that would recreate an event.
 * @visibility public
 * @example Inspecting the named event
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW CREATE EVENT app.item');
 *     [$statement->event->parts, $statement->resultColumns()[0]->name] // => [['app', 'item'], 'Event']
 */
final class ShowCreateEventStatement extends InspectionStatement
{
    /**
     * @param QualifiedName $event Optionally database-qualified event name
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $event)
    {
        if (count($event->parts) > 2) {
            throw new InvalidStructure('A event name takes at most a database qualifier.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->event);
    }

    /**
     * Names another event.
     */
    public function withEvent(QualifiedName $event): self
    {
        return $this->changed(new self($this->origin, $event));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateEventField::cases());
    }
}
