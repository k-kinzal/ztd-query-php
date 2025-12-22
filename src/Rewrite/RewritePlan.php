<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

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

    /**
     * Unknown identifier (table or column name) when kind is UNKNOWN_SCHEMA.
     *
     * @var string|null
     */
    private ?string $unknownIdentifier;

    /**
     * @param string $sql Rewritten SQL.
     * @param QueryKind $kind Classified kind of the statement.
     * @param ShadowMutation|null $mutation Optional mutation to apply after execution.
     * @param string|null $unknownIdentifier Unknown table/column name for UNKNOWN_SCHEMA.
     */
    public function __construct(
        string $sql,
        QueryKind $kind,
        ?ShadowMutation $mutation = null,
        ?string $unknownIdentifier = null
    ) {
        $this->sql = $sql;
        $this->kind = $kind;
        $this->mutation = $mutation;
        $this->unknownIdentifier = $unknownIdentifier;
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
     * Get the unknown identifier (table or column name) for UNKNOWN_SCHEMA plans.
     */
    public function unknownIdentifier(): ?string
    {
        return $this->unknownIdentifier;
    }
}
