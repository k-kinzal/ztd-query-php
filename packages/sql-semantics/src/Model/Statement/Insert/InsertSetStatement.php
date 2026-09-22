<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Insert;

use Override;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\InsertMode;

/**
 * Insertion from ordered column assignments.
 * @visibility public
 */
final class InsertSetStatement extends InsertStatement
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Write\Assignment> Validated ordered operands
     */
    public readonly array $writes;

    /**
     * @param list<\SqlSemantics\Model\Write\Assignment> $writes
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<\SqlSemantics\Model\Write\ConflictAction> $conflicts
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        Insertion $insertion,
        array $writes,
        InsertMode $mode = InsertMode::Insert,
        array $outputs = [],
        array $conflicts = [],
        ?\SqlSemantics\Model\Query\WithClause $ctes = null,
        ?\SqlSemantics\Model\Write\Policy\InsertPolicy $policy = null,
    ) {
        if ($writes === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('InsertSetStatement requires its writes.');
        }
        \SqlSemantics\Model\Validation\Collections::objects($writes, \SqlSemantics\Model\Write\Assignment::class);
        parent::__construct($origin, $insertion, $mode, $outputs, $conflicts, $ctes, $policy);
        $this->writes = \SqlSemantics\Model\Validation\Collections::nonEmpty($writes);
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->insertion, $this->writes, $this->mode, $this->outputs, $this->conflicts, $this->ctes, $this->policy);
    }



    /**

     * @param list<\SqlSemantics\Model\OutputColumn> $outputs

     */
    #[Override]
    public function withReturning(array $outputs): static
    {
        return $this->changed(new self($this->origin, $this->insertion, $this->writes, $this->mode, $outputs, $this->conflicts, $this->ctes, $this->policy));
    }
}
