<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTriggerField;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE statement that would recreate a trigger.
 * @visibility public
 * @example Inspecting the named trigger
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW CREATE TRIGGER app.item');
 *     [$statement->trigger->parts, $statement->resultColumns()[0]->name] // => [['app', 'item'], 'Trigger']
 */
final class ShowCreateTriggerStatement extends InspectionStatement
{
    /**
     * @param QualifiedName $trigger Optionally database-qualified trigger name
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $trigger)
    {
        if (count($trigger->parts) > 2) {
            throw new InvalidStructure('A trigger name takes at most a database qualifier.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->trigger);
    }

    /**
     * Names another trigger.
     */
    public function withTrigger(QualifiedName $trigger): self
    {
        return $this->changed(new self($this->origin, $trigger));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateTriggerField::cases());
    }
}
