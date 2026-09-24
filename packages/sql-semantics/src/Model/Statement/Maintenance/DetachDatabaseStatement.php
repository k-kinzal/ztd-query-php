<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;

/**
 * Typed DetachDatabaseStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Detaching a database
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind('DETACH DATABASE archive', strict: false);
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\DetachDatabaseStatement // => true
 *     $statement->schema->spelling() // => 'archive'
 */
final class DetachDatabaseStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Expression $schema,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Detach;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->schema);
    }



}
