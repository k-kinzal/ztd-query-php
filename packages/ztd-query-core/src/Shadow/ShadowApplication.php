<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Exception\SimulationException;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\MutationImpact;
use ZtdQuery\Shadow\Mutation\Row\ResultSetMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;

/**
 * Writes one simulated statement into the shadow, all of it or none of it.
 *
 * A statement that is refused part-way through must leave the shadow as it
 * was, or every later statement is simulated against a state the database
 * would never have been in, so the shadow is taken back to the snapshot
 * before the refusal is passed on.
 *
 * What comes out through here is a driver-shaped failure. A caller went
 * through an adapter that looks like PDO or mysqli, and a simulation-specific
 * exception surfacing there would be a type it has no reason to know about.
 */
final class ShadowApplication
{
    /**
     * @param ShadowStore $shadowStore Shadow the statement is written into
     * @param ReferentialIntegrityEnforcer $referentialIntegrity Carries the consequences outward
     * @param TableDefinitionRegistry $registry Answers what a table declares
     * @param SqlRewriter $rewriter Rewriter whose own state moves on with a committed insert
     */
    public function __construct(
        private readonly ShadowStore $shadowStore,
        private readonly ReferentialIntegrityEnforcer $referentialIntegrity,
        private readonly TableDefinitionRegistry $registry,
        private readonly SqlRewriter $rewriter,
    ) {
    }

    /**
     * Writes a mutation into the shadow and answers what it came to.
     *
     * @param ShadowMutation $mutation Mutation to write
     * @param ResultSet $resultSet What the rewritten statement read back
     * @param string $sql Statement being simulated, for the refusal
     *
     * @return MutationImpact What the statement came to
     *
     * @throws DatabaseException When the shadow refuses the statement
     */
    public function apply(ShadowMutation $mutation, ResultSet $resultSet, string $sql): MutationImpact
    {
        $before = $this->shadowStore->get($mutation->tableName());
        $snapshot = $this->shadowStore->snapshot();
        try {
            if ($mutation instanceof ResultSetMutation) {
                $mutation->applyResultSet($this->shadowStore, $resultSet);
            } else {
                $mutation->apply($this->shadowStore, $resultSet->rows);
            }
            $this->referentialIntegrity->synchronize(
                $snapshot,
                $this->shadowStore,
                $mutation,
                $resultSet->rows,
                $sql,
            );
        } catch (SimulationException $refusal) {
            $this->shadowStore->restore($snapshot);

            throw new DatabaseException($refusal->getMessage(), null, 0, $refusal);
        }

        $impact = new MutationImpact(
            $mutation,
            $before,
            $resultSet->rows,
            $this->shadowStore->get($mutation->tableName()),
        );
        if ($impact->isInsertLike() && $this->rewriter instanceof RewriteStateCommitter) {
            $this->rewriter->commitRewriteState();
        }

        return $impact;
    }

    /**
     * Answers the identity a driver would report for a simulated insert.
     *
     * A table says which column a database numbers; where it says nothing and
     * the key is one column, that column is what a database would have
     * numbered. Only the last row counts, which is what lastInsertId() means.
     *
     * @param ShadowMutation $mutation Mutation that was written
     * @param MutationImpact $impact What it came to
     *
     * @return string|null The identity, or null when nothing was inserted or nothing was numbered
     */
    public function lastInsertIdOf(ShadowMutation $mutation, MutationImpact $impact): ?string
    {
        if (!$impact->isInsertLike()) {
            return null;
        }

        $definition = $this->registry->get($mutation->tableName());
        $identityColumns = $definition !== null ? array_keys($definition->identityStrategies) : [];
        if ($identityColumns === [] && $definition !== null && count($definition->primaryKeys) === 1) {
            $identityColumns = $definition->primaryKeys;
        }
        $identityColumn = $identityColumns[0] ?? null;
        $returningRows = $impact->returningRows();
        $lastRow = $returningRows[count($returningRows) - 1] ?? null;
        $identityValue = $identityColumn !== null && $lastRow !== null
            ? ($lastRow[$identityColumn] ?? null)
            : null;

        return is_int($identityValue) || is_string($identityValue) ? (string) $identityValue : null;
    }
}
