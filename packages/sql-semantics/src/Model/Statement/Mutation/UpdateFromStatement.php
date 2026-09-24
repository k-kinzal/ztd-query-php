<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Mutation;

use Override;

/**
 * Update a table using mandatory additional input.
 *
 * @visibility public
 * @example Updating from another relation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET n=s.n FROM s WHERE t.id=s.id');
 *     $statement instanceof \SqlSemantics\Model\Statement\Mutation\UpdateFromStatement // => true
 *     $statement->target->declaration->name // => 't'
 */
final class UpdateFromStatement extends \SqlSemantics\Model\Statement\UpdateStatement
{
    /**
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\TableUse $target,
        public readonly \SqlSemantics\Model\TableUse|\SqlSemantics\Model\Join $from,
        array $writes,
        ?\SqlSemantics\Model\Expression $where = null,
        array $outputs = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        public readonly \SqlSemantics\Model\Write\Policy\ConstraintResponse $onViolation = \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default,
    ) {
        parent::__construct($origin, $writes, $where, $outputs, $ctes);
        \SqlSemantics\Model\Validation\StatementOperands::relation($target, $origin->dialect);
        \SqlSemantics\Model\Validation\StatementOperands::relation($from, $origin->dialect);
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->target, $this->from, $this->writes, $this->where, $this->outputs, $this->ctes, $this->onViolation);
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
        return $this->changed(new self($this->origin, $this->target, $this->from, $this->writes, $where, $this->outputs, $this->ctes, $this->onViolation));
    }

    /**
     * @param non-empty-list<\SqlSemantics\Model\Write\Assignment> $writes
     */
    #[Override]
    public function withAssignments(array $writes): static
    {
        return $this->changed(new self($this->origin, $this->target, $this->from, $writes, $this->where, $this->outputs, $this->ctes, $this->onViolation));
    }
}
