<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\ReindexObjectKind;
use SqlSemantics\Model\Maintenance\ReindexOptions;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rebuilds indexes of a required PostgreSQL index, table or schema.
 * @visibility public
 * @example Binding a rebuild request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('REINDEX TABLE t');
 *     $statement instanceof \SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement // => true
 */
final class ReindexObjectStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ReindexObjectKind $targetKind, public readonly QualifiedName $target, public readonly ReindexOptions $options = new ReindexOptions())
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This reindex form requires PostgreSql.');
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
        return new static($origin, $this->targetKind, $this->target, $this->options);
    }
}
