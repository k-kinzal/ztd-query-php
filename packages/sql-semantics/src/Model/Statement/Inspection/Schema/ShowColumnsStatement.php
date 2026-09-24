<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Schema;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Schema\ColumnField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes the columns of a table; FULL adds collation, privileges, and comment, and EXTENDED includes hidden columns.
 * @visibility public
 * @example Inspecting the described table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE users(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SHOW FULL COLUMNS FROM users LIKE 'id%'");
 *     [$statement->table->declaration->name, $statement->full, count($statement->resultColumns())] // => ['users', true, 9]
 */
final class ShowColumnsStatement extends InspectionStatement
{
    /**
     * @param TableReference $table Described table, resolved or diagnosed against the schema
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table, public readonly PatternFilter|ConditionFilter|null $filter = null, public readonly bool $full = false, public readonly bool $extended = false)
    {
        InspectedTable::validate($origin, $table);
        InspectedTable::extended($origin, $extended);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table, $this->filter, $this->full, $this->extended);
    }

    /**
     * Describes another table and resolves it against this request's schema.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->filter, $this->full, $this->extended));
    }

    /**
     * Replaces the column-name restriction; null describes every column.
     */
    public function withFilter(PatternFilter|ConditionFilter|null $filter): self
    {
        return $this->changed(new self($this->origin, $this->table, $filter, $this->full, $this->extended));
    }

    /**
     * Requests or omits the collation, privilege, and comment fields.
     */
    public function withFull(bool $full): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->filter, $full, $this->extended));
    }

    /**
     * Includes or omits hidden columns.
     */
    public function withExtended(bool $extended): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->filter, $this->full, $extended));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(ColumnField::listing($this->full));
    }
}
