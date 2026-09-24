<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Insert;

use Override;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\InsertMode;

/**
 * Insertion from an explicit VALUES row list.
 * @visibility public
  * @example Inspecting InsertValuesStatement
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER PRIMARY KEY)'));
 *     $statement = $binder->bind('INSERT INTO t(id) VALUES(missing) ON CONFLICT(id) DO UPDATE SET id=EXCLUDED.id WHERE t.id>0 RETURNING id', strict: false);
 *     $statement instanceof \SqlSemantics\Model\Statement\Insert\InsertValuesStatement // => true
 */
final class InsertValuesStatement extends InsertStatement
{
    /**
     * @var non-empty-list<list<\SqlSemantics\Model\Expression|\SqlSemantics\Model\Write\DefaultSource>> Validated ordered operands
     */
    public readonly array $rows;

    /**
     * @param list<list<\SqlSemantics\Model\Expression|\SqlSemantics\Model\Write\DefaultSource>> $rows
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<\SqlSemantics\Model\Write\ConflictAction> $conflicts
     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        Insertion $insertion,
        array $rows,
        InsertMode $mode = InsertMode::Insert,
        array $outputs = [],
        array $conflicts = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        ?\SqlSemantics\Model\Write\Policy\InsertPolicy $policy = null,
    ) {
        \SqlSemantics\Model\Validation\RowShape::writes($rows, $origin->dialect);
        \SqlSemantics\Model\Validation\RowShape::insertion($insertion, count($rows[0]), $origin->dialect);
        parent::__construct($origin, $insertion, $mode, $outputs, $conflicts, $ctes, $policy);
        $this->rows = \SqlSemantics\Model\Validation\Collections::nonEmpty($rows);
    }

    /**
     * VALUES and SET insertions can name their proposed row.
     */
    #[Override]
    protected function namesProposedRow(): bool
    {
        return true;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->insertion, $this->rows, $this->mode, $this->outputs, $this->conflicts, $this->ctes, $this->policy);
    }



    /**

     * @param non-empty-list<list<\SqlSemantics\Model\Expression|\SqlSemantics\Model\Write\DefaultSource>> $rows

     */
    public function withRows(array $rows): self
    {
        return $this->changed(new self($this->origin, $this->insertion, $rows, $this->mode, $this->outputs, $this->conflicts, $this->ctes, $this->policy));
    }
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     */
    #[Override]
    public function withReturning(array $outputs): static
    {
        return $this->changed(new self($this->origin, $this->insertion, $this->rows, $this->mode, $outputs, $this->conflicts, $this->ctes, $this->policy));
    }
}
