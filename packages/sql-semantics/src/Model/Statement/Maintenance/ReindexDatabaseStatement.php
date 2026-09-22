<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\DatabaseIndexScope;
use SqlSemantics\Model\Maintenance\ReindexOptions;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rebuilds a selected class of indexes in the current database.
 * @visibility public
 * @example Binding a rebuild request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('REINDEX SYSTEM');
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\ReindexDatabaseStatement // => true
 */
final class ReindexDatabaseStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DatabaseIndexScope $selection, public readonly ?string $database = null, public readonly ReindexOptions $options = new ReindexOptions())
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This reindex form requires PostgreSql.');
        }
        if ($selection === DatabaseIndexScope::SystemTables && $options->concurrently) {
            throw new InvalidStructure('System-table indexes cannot be rebuilt concurrently.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the reindex operation identity.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Reindex;
    }

    /**
     * Preserves rebuild targets and policies while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->selection, $this->database, $this->options);
    }
}
