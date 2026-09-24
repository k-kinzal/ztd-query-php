<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\PartitionValidation;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Applies an ordered list of typed alterations to one MySQL table under the requested algorithm, lock level, and validation.
 * Partition and tablespace commands stand alone, a partitioning change comes last, and an empty list requests no change.
 * WITH or WITHOUT VALIDATION exists from MySQL 5.7, INSTANT from MySQL 8.0, and IGNORE only in MySQL 5.6.
 * @visibility public
 * @example Reading combined alterations
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALGORITHM = INPLACE, LOCK = NONE, ADD COLUMN n INT, ADD INDEX ix (n)');
 *     [$statement->table->declaration->name, count($statement->alterations)] // => ['t', 2]
 *     $statement->lock // => \SqlSemantics\Model\Definition\IndexLock::None
 *     $statement->toString() // => 'ALTER TABLE `t` ALGORITHM = INPLACE, LOCK = NONE, ADD COLUMN `n` integer, ADD INDEX `ix`(`n`)'
 * @example Rejecting a partition command combined with another alteration
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t TRUNCATE PARTITION ALL');
 *     $statement->withAlterations([...$statement->alterations, \SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand::Force]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterTableStatement extends BoundStatement
{
    /**
     * @param list<TableAlteration> $alterations Alterations in request order
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableReference $table,
        public readonly array $alterations = [],
        public readonly TableAlgorithm $algorithm = TableAlgorithm::Default,
        public readonly IndexLock $lock = IndexLock::Default,
        public readonly ?PartitionValidation $validation = null,
        public readonly bool $ignore = false,
    ) {
        TableInvariant::table($origin, $table);
        Collections::objects($alterations, TableAlteration::class);
        foreach ($alterations as $alteration) {
            AlterationRules::release($origin, $alteration);
        }
        AlterationRules::combination($origin, $alterations, $algorithm, $lock);
        if ($algorithm === TableAlgorithm::Instant) {
            TableInvariant::modern($origin, 'ALGORITHM = INSTANT');
        }
        if ($validation !== null) {
            TableInvariant::since57($origin, 'WITH or WITHOUT VALIDATION');
        }
        if ($ignore) {
            TableInvariant::only($origin, ['mysql-5.6.51'], 'ALTER IGNORE TABLE');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table, $this->alterations, $this->algorithm, $this->lock, $this->validation, $this->ignore);
    }

    /**
     * Replaces the altered table.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->alterations, $this->algorithm, $this->lock, $this->validation, $this->ignore));
    }

    /**
     * Replaces the ordered alterations in a separately validated statement.
     * @param list<TableAlteration> $alterations
     */
    public function withAlterations(array $alterations): self
    {
        return $this->changed(new self($this->origin, $this->table, $alterations, $this->algorithm, $this->lock, $this->validation, $this->ignore));
    }

    /**
     * Replaces the requested algorithm.
     */
    public function withAlgorithm(TableAlgorithm $algorithm): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->alterations, $algorithm, $this->lock, $this->validation, $this->ignore));
    }

    /**
     * Replaces the requested lock level.
     */
    public function withLock(IndexLock $lock): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->alterations, $this->algorithm, $lock, $this->validation, $this->ignore));
    }

    /**
     * Replaces the requested partition validation.
     */
    public function withValidation(?PartitionValidation $validation): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->alterations, $this->algorithm, $this->lock, $validation, $this->ignore));
    }
}
