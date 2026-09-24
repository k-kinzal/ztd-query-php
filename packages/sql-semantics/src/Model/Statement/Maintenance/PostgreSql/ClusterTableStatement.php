<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rewrites one table in the order of an index; without an index the table's recorded clustering index is used.
 * The pre-8.3 form CLUSTER index ON table binds to the same operands.
 * @visibility public
 * @example Reading the table and index
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CLUSTER t_a ON t');
 *     [$statement->table->declaration->name, $statement->index, $statement->toString()] // => ['t', 't_a', 'CLUSTER "public"."t" USING "t_a"']
 */
final class ClusterTableStatement extends BoundStatement
{
    /**
     * @param TableReference $table Resolved or diagnosed table
     * @param string|null $index Index name in the table's schema; null uses the recorded clustering index
     * @param bool $verbose Whether progress messages are reported
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $table, public readonly ?string $index = null, public readonly bool $verbose = false)
    {
        MaintenanceTargets::validate($origin, [new MaintenanceTarget($table)]);
        if ($index === '') {
            throw new InvalidStructure('A clustering index requires a nonempty name.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Cluster;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->table, $this->index, $this->verbose);
    }

    /**
     * Clusters another table.
     */
    public function withTable(TableReference $table): self
    {
        return $this->changed(new self($this->origin, $table, $this->index, $this->verbose));
    }

    /**
     * Selects the ordering index; null uses the recorded clustering index.
     */
    public function withIndex(?string $index): self
    {
        return $this->changed(new self($this->origin, $this->table, $index, $this->verbose));
    }

    /**
     * Selects whether progress messages are reported.
     */
    public function withVerbose(bool $verbose): self
    {
        return $this->changed(new self($this->origin, $this->table, $this->index, $verbose));
    }
}
