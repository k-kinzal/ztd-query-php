<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;

/**
 * Typed UseDatabaseStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Selecting the default database
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('USE app');
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\UseDatabaseStatement // => true
 *     $statement->database->parts // => ['app']
 */
final class UseDatabaseStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $database,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Use;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->database);
    }



}
