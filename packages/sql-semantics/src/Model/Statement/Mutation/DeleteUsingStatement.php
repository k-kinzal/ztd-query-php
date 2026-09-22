<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Mutation;

use Override;

/**
 * Delete a table using mandatory additional input.
 *
 * @visibility public
  * @example Inspecting DeleteUsingStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)')))->bind('DELETE FROM t USING s WHERE t.id=s.id');
 *     $statement instanceof \SqlSemantics\Model\Statement\Mutation\DeleteUsingStatement // => true
 */
final class DeleteUsingStatement extends \SqlSemantics\Model\Statement\DeleteStatement
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\TableUse $target,
        public readonly \SqlSemantics\Model\TableUse|\SqlSemantics\Model\Join $using,
        ?\SqlSemantics\Model\Expression $where = null,
        array $outputs = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
    ) {
        parent::__construct($origin, $where, $outputs, $ctes);
        \SqlSemantics\Model\Validation\StatementOperands::relation($target, $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::relation($using, $origin->dialect);
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->target, $this->using, $this->where, $this->outputs, $this->ctes);
    }

    /**
     * @return non-empty-list<\SqlSemantics\Model\TableUse>
     */
    #[Override]
    public function affectedTables(): array
    {
        return [$this->target];
    }



    /**
     * Rebinds an immutable predicate replacement in this operation's scope.
     */
    #[Override]
    public function withWhere(?\SqlSemantics\Model\Expression $where): static
    {
        return $this->changed(new self($this->origin, $this->target, $this->using, $where, $this->outputs, $this->ctes));
    }
}
