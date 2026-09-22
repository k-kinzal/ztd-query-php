<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rebuilds the named SQLite table, index or collation indexes.
 * @visibility public
 * @example Binding a rebuild request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('REINDEX ix');
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\ReindexNamedStatement // => true
 */
final class ReindexNamedStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $target)
    {
        if ($origin->dialect !== Dialect::Sqlite) {
            throw new InvalidStructure('This reindex form requires Sqlite.');
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
        return new static($origin, $this->target);
    }
}
