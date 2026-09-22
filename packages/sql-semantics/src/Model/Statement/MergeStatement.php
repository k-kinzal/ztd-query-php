<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed MergeStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
  * @example Inspecting MergeStatement
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER); CREATE TABLE s(id INTEGER,n INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED AND s.n>0 THEN UPDATE SET n=s.n WHEN NOT MATCHED THEN INSERT(id,n) VALUES(s.id,s.n) RETURNING t.id');
 *     $statement instanceof \SqlSemantics\Model\Statement\MergeStatement // => true
 */
final class MergeStatement extends \SqlSemantics\Model\BoundStatement implements \SqlSemantics\Model\ResultStatement
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Write\Merge $merge,
        public readonly array $outputs = [],
        public readonly ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\StatementOperands::relation($merge->target, $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::relation($merge->input, $origin->dialect);
        if ($origin->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('MERGE requires the PostgreSQL dialect.');
        }
        \SqlSemantics\Model\Validation\StatementOperands::ctes($ctes, $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::outputs($outputs, $origin->dialect, true);
        \SqlSemantics\Model\Validation\StatementOperands::expressions([$merge->condition], $origin->dialect);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Merge;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->merge, $this->outputs, $this->ctes);
    }

    /**

     * @return list<\SqlSemantics\Model\OutputColumn>

     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->outputs;
    }


    /**
     * Sets the match condition and recomputes the complete conditional write plan.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withCondition(\SqlSemantics\Model\Expression $condition): self
    {
        return $this->replaceExpression($this->merge->condition, $condition);
    }

    /**
     * Identifies the storage relation affected by this operation.
     *
     * @return non-empty-list<\SqlSemantics\Model\TableUse>
     */
    public function affectedTables(): array
    {
        return [$this->merge->target];
    }

}
