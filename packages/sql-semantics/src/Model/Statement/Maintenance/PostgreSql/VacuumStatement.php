<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reclaims storage and optionally collects statistics for the listed relations, or for every relation when none is listed.
 * Legacy keyword options such as VACUUM FULL FREEZE VERBOSE ANALYZE bind to the same options as the parenthesized list.
 * @visibility public
 * @example Reading the targets and options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('VACUUM (ANALYZE, PARALLEL 2) t (a)');
 *     [$statement->options->parallel, $statement->targets[0]->columns, (new \SqlSemantics\SimpleSerializer())->serialize($statement)] // => [2, ['a'], 'VACUUM(ANALYZE, PARALLEL 2) "public"."t"("a")']
 * @example Rejecting a column list without ANALYZE
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     (new \SqlSemantics\Binder($schema))->bind('VACUUM t (a)'); // throws \SqlSemantics\InvalidSql
 */
final class VacuumStatement extends BoundStatement
{
    /**
     * @param list<MaintenanceTarget> $targets Relations in written order; column lists require the analyze option
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly VacuumOptions $options = new VacuumOptions(), public readonly array $targets = [])
    {
        MaintenanceTargets::validate($origin, $targets);
        foreach ($targets as $target) {
            if ($target->columns !== [] && !$options->analyze) {
                throw new InvalidStructure('A VACUUM column list requires the ANALYZE option.');
            }
        }
        if ($options->onlyDatabaseStats && $targets !== []) {
            throw new InvalidStructure('ONLY_DATABASE_STATS cannot be specified with a list of tables.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Vacuum;
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
    public function withOptions(VacuumOptions $options): self
    {
        return $this->changed(new self($this->origin, $options, $this->targets));
    }

    /**
     * Replaces the processed relations; an empty list processes every relation.
     * @param list<MaintenanceTarget> $targets
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $this->options, $targets));
    }
}
