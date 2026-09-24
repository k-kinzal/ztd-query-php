<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Schema;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Schema\ColumnField;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * DESCRIBE or DESC of a table: the SHOW COLUMNS fields of its columns, optionally restricted to names matching a LIKE pattern.
 * @visibility public
 * @example Describing the columns that match a pattern
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE users(id INT, name TEXT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('DESC users id');
 *     [$statement->table->declaration->name, $statement->pattern, count($statement->resultColumns())] // => ['users', 'id', 6]
 */
final class DescribeTableStatement extends InspectionStatement
{
    /**
     * @param TableReference $table Described table, resolved or diagnosed against the schema
     * @param string|null $pattern LIKE pattern for column names; a column identifier is used as the same pattern; null describes every column
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table, public readonly ?string $pattern = null)
    {
        InspectedTable::validate($origin, $table);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Describe;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table, $this->pattern);
    }

    /**
     * Describes another table and resolves it against this request's schema.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->pattern));
    }

    /**
     * Replaces the column-name pattern; null describes every column.
     */
    public function withPattern(?string $pattern): self
    {
        return $this->changed(new self($this->origin, $this->table, $pattern));
    }

    /**
     * @return list<OutputColumn> The SHOW COLUMNS fields without the FULL detail
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(ColumnField::listing(false));
    }
}
