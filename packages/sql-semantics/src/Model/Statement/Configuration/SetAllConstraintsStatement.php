<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets the checking time for all deferrable constraints in this transaction.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SET CONSTRAINTS ALL DEFERRED');
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement // => true
 * @visibility public
 */
final class SetAllConstraintsStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly \SqlSemantics\Model\Configuration\ConstraintTiming $timing)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SetAllConstraintsStatement requires PostgreSql.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->timing);
    }
}
