<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateTableField;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE TABLE statement that would recreate a table.
 * @visibility public
 * @example Inspecting the described table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE users(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SHOW CREATE TABLE users');
 *     [$statement->table->declaration->name, $statement->resultColumns()[1]->name] // => ['users', 'Create Table']
 */
final class ShowCreateTableStatement extends InspectionStatement
{
    /**
     * @param TableReference $table Described table, resolved or diagnosed against the schema
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table)
    {
        InspectedTable::validate($origin, $table);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table);
    }

    /**
     * Describes another table and resolves it against this request's schema.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateTableField::cases());
    }
}
