<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Schema;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Schema\IndexField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the indexes of a table; EXTENDED includes hidden key parts. Only a WHERE condition can restrict the listing.
 * @visibility public
 * @example Inspecting the listed table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE users(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SHOW EXTENDED KEYS FROM users WHERE Non_unique = 0');
 *     [$statement->table->declaration->name, $statement->extended, $statement->resultColumns()[1]->name] // => ['users', true, 'Non_unique']
 */
final class ShowIndexesStatement extends InspectionStatement
{
    /**
     * @param TableReference $table Listed table, resolved or diagnosed against the schema
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table, public readonly ?ConditionFilter $condition = null, public readonly bool $extended = false)
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
        return new self($origin, $this->table, $this->condition, $this->extended);
    }

    /**
     * Lists another table and resolves it against this request's schema.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->condition, $this->extended));
    }

    /**
     * Replaces the restriction; null lists every index.
     */
    public function withCondition(?ConditionFilter $condition): self
    {
        return $this->changed(new self($this->origin, $this->table, $condition, $this->extended));
    }

    /**
     * Includes or omits hidden key parts.
     */
    public function withExtended(bool $extended): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->condition, $extended));
    }

    /**
     * @return list<OutputColumn> Index facts; visibility and expression fields exist from MySQL 8.0
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(IndexField::listing($this->legacyRelease()));
    }
}
