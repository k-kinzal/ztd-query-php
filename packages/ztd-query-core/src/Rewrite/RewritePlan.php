<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\ShadowMutation;

/**
 * Represents the rewrite outcome for a single SQL statement.
 */
final class RewritePlan
{
    /**
     * Rewritten SQL string.
     *
     * @var string
     */
    private string $sql;

    /**
     * Classified kind of the SQL statement.
     *
     * @var QueryKind
     */
    private QueryKind $kind;

    /**
     * Mutation to apply after execution when simulating writes.
     *
     * @var ShadowMutation|null
     */
    private ?ShadowMutation $mutation;

    private ?ReturningProjection $returningProjection;

    private AffectedRowsMode $affectedRowsMode;

    /**
     * @param string $sql Rewritten SQL.
     * @param QueryKind $kind Classified kind of the statement.
     * @param ShadowMutation|null $mutation Optional mutation to apply after execution.
     * @param ReturningProjection|null $returningProjection Optional client-visible write projection.
     * @param AffectedRowsMode $affectedRowsMode Observable affected-row convention.
     */
    public function __construct(
        string $sql,
        QueryKind $kind,
        ?ShadowMutation $mutation = null,
        ?ReturningProjection $returningProjection = null,
        AffectedRowsMode $affectedRowsMode = AffectedRowsMode::Changed,
    ) {
        $this->sql = $sql;
        $this->kind = $kind;
        $this->mutation = $mutation;
        $this->returningProjection = $returningProjection;
        $this->affectedRowsMode = $affectedRowsMode;
    }

    /**
     * Get rewritten SQL.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * Get statement kind for routing decisions.
     */
    public function kind(): QueryKind
    {
        return $this->kind;
    }

    /**
     * Get the mutation for write simulation, if any.
     */
    public function mutation(): ?ShadowMutation
    {
        return $this->mutation;
    }

    /**
     * Answers the mutation a simulated write must carry.
     *
     * A plan whose kind says the write was simulated and that carries no
     * mutation describes nothing the shadow could be told, so the statement
     * is refused rather than read back as though it had been simulated.
     *
     * @return ShadowMutation The mutation
     *
     * @throws UnsupportedSqlException When the plan carries none
     */
    public function requireMutation(): ShadowMutation
    {
        if ($this->mutation === null) {
            throw new UnsupportedSqlException($this->sql, 'Unsimulatable write');
        }

        return $this->mutation;
    }

    public function returningProjection(): ?ReturningProjection
    {
        return $this->returningProjection;
    }

    public function affectedRowsMode(): AffectedRowsMode
    {
        return $this->affectedRowsMode;
    }
}
