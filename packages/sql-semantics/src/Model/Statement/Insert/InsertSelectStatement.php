<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Insert;

use Override;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\InsertMode;

/**
 * Insertion from exactly one source query.
 * @visibility public
  * @example Inspecting InsertSelectStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t (a INTEGER, b TEXT)')))->bind("INSERT INTO t(b,a) SELECT 'x',1");
 *     $statement instanceof \SqlSemantics\Model\Statement\Insert\InsertSelectStatement // => true
 */
final class InsertSelectStatement extends InsertStatement
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<\SqlSemantics\Model\Write\ConflictAction> $conflicts
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        Insertion $insertion,
        public readonly \SqlSemantics\Model\BoundQuery $query,
        InsertMode $mode = InsertMode::Insert,
        array $outputs = [],
        array $conflicts = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        ?\SqlSemantics\Model\Write\Policy\InsertPolicy $policy = null,
    ) {
        if ($query->origin->dialect !== $origin->dialect) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An insertion query must use the destination dialect.');
        }
        \SqlSemantics\Model\Validation\RowShape::insertion($insertion, \SqlSemantics\Model\Validation\RowShape::width($query), $origin->dialect);
        parent::__construct($origin, $insertion, $mode, $outputs, $conflicts, $ctes, $policy);
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->insertion, $this->query, $this->mode, $this->outputs, $this->conflicts, $this->ctes, $this->policy);
    }



    /**
     * Replaces the required insertion query and validates the complete write against its schema.
     */
    public function withQuery(\SqlSemantics\Model\BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $this->insertion, $query, $this->mode, $this->outputs, $this->conflicts, $this->ctes, $this->policy));
    }
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     */
    #[Override]
    public function withReturning(array $outputs): static
    {
        return $this->changed(new self($this->origin, $this->insertion, $this->query, $this->mode, $outputs, $this->conflicts, $this->ctes, $this->policy));
    }
}
