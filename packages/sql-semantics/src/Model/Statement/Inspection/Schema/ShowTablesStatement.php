<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Schema;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Schema\TableField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the tables and views of a database; FULL adds the object type and EXTENDED includes hidden tables.
 * @visibility public
 * @example Inspecting the result label of a database listing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW FULL TABLES FROM app LIKE 'user%'");
 *     $statement->resultColumns()[0]->name // => 'Tables_in_app'
 */
final class ShowTablesStatement extends InspectionStatement
{
    /**
     * @param string|null $database Listed database; null selects the current database
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?string $database = null, public readonly PatternFilter|ConditionFilter|null $filter = null, public readonly bool $full = false, public readonly bool $extended = false)
    {
        if ($database === '') {
            throw new InvalidStructure('A database name requires at least one character.');
        }
        InspectedTable::extended($origin, $extended);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->database, $this->filter, $this->full, $this->extended);
    }

    /**
     * Selects another database; null selects the current database.
     */
    public function withDatabase(?string $database): self
    {
        return $this->changed(new self($this->origin, $database, $this->filter, $this->full, $this->extended));
    }

    /**
     * Replaces the name restriction; null lists every table.
     */
    public function withFilter(PatternFilter|ConditionFilter|null $filter): self
    {
        return $this->changed(new self($this->origin, $this->database, $filter, $this->full, $this->extended));
    }

    /**
     * Requests or omits the object type field.
     */
    public function withFull(bool $full): self
    {
        return $this->changed(new self($this->origin, $this->database, $this->filter, $full, $this->extended));
    }

    /**
     * Includes or omits hidden tables.
     */
    public function withExtended(bool $extended): self
    {
        return $this->changed(new self($this->origin, $this->database, $this->filter, $this->full, $extended));
    }

    /**
     * @return list<OutputColumn> The name field labelled with the listed database, plus the type when FULL
     */
    #[Override]
    public function resultColumns(): array
    {
        $database = $this->database ?? $this->origin->context?->schema()->defaultSchema ?? '';
        return $this->columns($this->full ? TableField::cases() : [TableField::Name], [TableField::Name->label() => TableField::Name->label() . $database]);
    }
}
