<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction;

use Override;

/**
 * Typed RollbackToSavepointStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Rolling back to a savepoint
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind('ROLLBACK TO sp1');
 *     $statement instanceof \SqlSemantics\Model\Statement\Transaction\RollbackToSavepointStatement // => true
 *     $statement->toString() // => 'ROLLBACK TO SAVEPOINT "sp1"'
 */
final class RollbackToSavepointStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly string $name,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Rollback;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->name);
    }



}
