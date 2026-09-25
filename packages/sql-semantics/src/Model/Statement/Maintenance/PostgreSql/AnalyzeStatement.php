<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Collects planner statistics for the listed relations and columns, or for every relation when none is listed.
 * @visibility public
 * @example Reading the targets
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ANALYSE VERBOSE t');
 *     [$statement->options->verbose, (new \SqlSemantics\SimpleSerializer())->serialize($statement)] // => [true, 'ANALYZE(VERBOSE) "public"."t"']
 */
final class AnalyzeStatement extends BoundStatement
{
    /**
     * @param list<MaintenanceTarget> $targets Relations in written order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AnalyzeOptions $options = new AnalyzeOptions(), public readonly array $targets = [])
    {
        MaintenanceTargets::validate($origin, $targets);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Analyze;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->options, $this->targets);
    }

    /**
     * Replaces the options.
     */
    public function withOptions(AnalyzeOptions $options): self
    {
        return $this->changed(new self($this->origin, $options, $this->targets));
    }

    /**
     * Replaces the analyzed relations; an empty list analyzes every relation.
     * @param list<MaintenanceTarget> $targets
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $this->options, $targets));
    }
}
