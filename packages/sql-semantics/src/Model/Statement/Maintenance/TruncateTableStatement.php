<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * A MySQL request to empty one physical table and reset its generated identity sequence.
 * @visibility public
 * @example Inspecting the affected table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('TRUNCATE TABLE t');
 *     $statement->table->declaration->name // => 't'
 */
final class TruncateTableStatement extends BoundStatement
{
    /**
     * Requires one unaliased MySQL table, without row predicates or query inputs.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table)
    {
        if ($origin->dialect !== Dialect::MySql || $table->alias !== null) {
            throw new InvalidStructure('This truncation requires one unaliased MySQL table.');
        }
        StatementOperands::relation($table, $origin->dialect);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Truncate;
    }

    /**
     * Retains the complete-table operation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->table);
    }

    /**
     * Changes the affected table while validating its identity in the current schema.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table));
    }
}
