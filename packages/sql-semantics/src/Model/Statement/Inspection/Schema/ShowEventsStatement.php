<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Schema;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Schema\EventField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists scheduled events of a database.
 * @visibility public
 * @example Inspecting the listed database
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW EVENTS FROM app LIKE 'daily%'");
 *     $statement->database // => 'app'
 */
final class ShowEventsStatement extends InspectionStatement
{
    /**
     * @param string|null $database Listed database; null selects the current database
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?string $database = null, public readonly PatternFilter|ConditionFilter|null $filter = null)
    {
        if ($database === '') {
            throw new InvalidStructure('A database name requires at least one character.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->database, $this->filter);
    }

    /**
     * Selects another database; null selects the current database.
     */
    public function withDatabase(?string $database): self
    {
        return $this->changed(new self($this->origin, $database, $this->filter));
    }

    /**
     * Replaces the restriction; null lists every entry.
     */
    public function withFilter(PatternFilter|ConditionFilter|null $filter): self
    {
        return $this->changed(new self($this->origin, $this->database, $filter));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(EventField::cases());
    }
}
