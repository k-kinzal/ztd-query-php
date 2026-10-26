<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

/**
 * Tracks object correspondence for one graph comparison, including shared references.
 *
 * @visibility SqlSemantics
 */
final class ReferencePairs
{
    /**
     * @var array<int, object> The right object paired with each left identity
     */
    private array $left = [];

    /**
     * @var array<int, object> The left object paired with each right identity
     */
    private array $right = [];

    /**
     * @var array<int, array<int, true>> Pairs whose members have already been queued
     */
    private array $visited = [];

    /**
     * Pairs two identities unless doing so would split or merge an existing reference.
     */
    public function accept(object $left, object $right): bool
    {
        $leftId = spl_object_id($left);
        $rightId = spl_object_id($right);
        if ((isset($this->left[$leftId]) && $this->left[$leftId] !== $right) || (isset($this->right[$rightId]) && $this->right[$rightId] !== $left)) {
            return false;
        }
        $this->left[$leftId] = $right;
        $this->right[$rightId] = $left;
        return true;
    }

    /**
     * Records a visit and answers whether this exact pair was already traversed.
     */
    public function seen(object $left, object $right): bool
    {
        $leftId = spl_object_id($left);
        $rightId = spl_object_id($right);
        $seen = isset($this->visited[$leftId][$rightId]);
        $this->visited[$leftId][$rightId] = true;
        return $seen;
    }
}
