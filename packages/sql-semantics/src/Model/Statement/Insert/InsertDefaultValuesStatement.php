<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Insert;

use Override;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\InsertMode;

/**
 * Insertion of one row using destination defaults.
 * @visibility public
  * @example Inspecting InsertDefaultValuesStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t (a INTEGER DEFAULT 2)')))->bind('INSERT INTO t DEFAULT VALUES');
 *     $statement instanceof \SqlSemantics\Model\Statement\Insert\InsertDefaultValuesStatement // => true
 */
final class InsertDefaultValuesStatement extends InsertStatement
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<\SqlSemantics\Model\Write\ConflictAction> $conflicts
     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        Insertion $insertion,
        InsertMode $mode = InsertMode::Insert,
        array $outputs = [],
        array $conflicts = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        ?\SqlSemantics\Model\Write\Policy\InsertPolicy $policy = null,
    ) {
        parent::__construct($origin, $insertion, $mode, $outputs, $conflicts, $ctes, $policy);
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->insertion, $this->mode, $this->outputs, $this->conflicts, $this->ctes, $this->policy);
    }



    /**

     * @param list<\SqlSemantics\Model\OutputColumn> $outputs

     */
    #[Override]
    public function withReturning(array $outputs): static
    {
        return $this->changed(new self($this->origin, $this->insertion, $this->mode, $outputs, $this->conflicts, $this->ctes, $this->policy));
    }
}
