<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;

/**
 * Typed AttachDatabaseStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 * @example Attaching a database file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind("ATTACH DATABASE 'archive.db' AS archive", strict: false);
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\AttachDatabaseStatement // => true
 *     $statement->database->spelling() // => "'archive.db'"
 */
final class AttachDatabaseStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        \SqlSemantics\Model\Statement\Origin $origin,
        public readonly \SqlSemantics\Model\Expression $database,
        public readonly \SqlSemantics\Model\Expression $schema,
        public readonly ?\SqlSemantics\Model\Expression $encryptionKey = null,
    ) {
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): \SqlSemantics\Model\Statement\StatementKind
    {
        return \SqlSemantics\Model\Statement\StatementKind::Attach;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(\SqlSemantics\Model\Statement\Origin $origin): static
    {
        return new static($origin, $this->database, $this->schema, $this->encryptionKey);
    }



}
