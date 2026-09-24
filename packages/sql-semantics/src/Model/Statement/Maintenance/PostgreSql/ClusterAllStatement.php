<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reclusters every previously clustered table the user may maintain, each on its recorded index.
 * @visibility public
 * @example Reading the request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CLUSTER VERBOSE');
 *     [$statement->verbose, $statement->toString()] // => [true, 'CLUSTER(VERBOSE)']
 */
final class ClusterAllStatement extends BoundStatement
{
    /**
     * @param bool $verbose Whether progress messages are reported
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly bool $verbose = false)
    {
        MaintenanceTargets::validate($origin, []);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Cluster;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->verbose);
    }

    /**
     * Selects whether progress messages are reported.
     */
    public function withVerbose(bool $verbose): self
    {
        return $this->changed(new self($this->origin, $verbose));
    }
}
