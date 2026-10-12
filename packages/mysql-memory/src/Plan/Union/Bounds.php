<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Union;

use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Combine\SetOperation;
use MySqlMemory\Plan\Path\SetCount;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Projection\Emission;
use MySqlMemory\Plan\Projection\FoundRows;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;

/**
 * Preserves legacy UNION operand bounds and the rows counted after materialization.
 * MySQL 5.6 also bounds the last unsorted operand; MySQL 5.7 streams pure UNION ALL.
 *
 * @visibility MySqlMemory
 */
final class Bounds
{
    /**
     * @param Planner $planner The planner of the query expression
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Applies legacy bounds, or returns null when the ordinary query path applies.
     */
    public function plan(AccessPath $path, QueryExpression $query, ?Scope $outer): ?AccessPath
    {
        if (!$this->planner->settings->legacy() || !$this->combined($path)) {
            return null;
        }
        $bound = $this->planner->blocks->limit($path, $query->limit, $outer);
        if (!$bound instanceof Limit) {
            return $bound;
        }
        $oldest = $this->planner->settings->release() === GrammarRelease::MySql5651;
        if (!$oldest && $this->streamed($path)) {
            return new \MySqlMemory\Plan\Path\Combine\UnionLimit((new Streaming())->apply($path), $bound->count, $bound->count === 0 ? 0 : $bound->offset, (new FoundRows())->requested($query));
        }
        $last = $oldest && $query->orderBy === [];
        $calculate = $last && (new FoundRows())->requested($query);
        $count = $bound->count === null ? null : ($bound->count > PHP_INT_MAX - $bound->offset ? PHP_INT_MAX : $bound->count + $bound->offset);
        $input = $this->buffered($path, $last, $count, $calculate);

        return new Limit($input, $bound->count, $bound->offset, initialize: true);
    }

    /**
     * Identifies a set underneath output conversion or global ordering.
     */
    public function combined(AccessPath $path): bool
    {
        while ($path instanceof Project || $path instanceof Sort) {
            $path = $path->input;
        }

        return $path instanceof SetOperation && $path->kind === SetKind::Union;
    }

    /**
     * Identifies an unordered chain with no DISTINCT materialization boundary.
     */
    public function streamed(AccessPath $path): bool
    {
        while ($path instanceof Project) {
            $path = $path->input;
        }
        if (!$path instanceof SetOperation) {
            return !$path instanceof Sort;
        }

        return $path->kind === SetKind::Union && !$path->distinct && $this->streamed($path->left) && $this->streamed($path->right);
    }

    /**
     * Captures the materialized count and optionally bounds the last operand's input.
     */
    public function buffered(AccessPath $path, bool $last, ?int $count, bool $calculate): AccessPath
    {
        if ($path instanceof Project) {
            return new Project($this->buffered($path->input, $last, $count, $calculate), $path->expressions, $path->append, $path->retainAt, $path->publish, $path->order, $path->initial);
        }
        if ($path instanceof Sort) {
            return new Sort($this->buffered($path->input, $last, $count, $calculate), $path->keys);
        }
        if (!$path instanceof SetOperation) {
            return $path;
        }
        $right = $last ? (new Emission($this->planner))->bounded(new Limit($path->right, $count, countFound: true, exhaust: $calculate), $path->right->width(), false) : $path->right;

        return new SetOperation($path->kind, $path->distinct, $path->left, $right, $path->domains, $path->retain, $calculate ? SetCount::RightInput : SetCount::Materialized);
    }
}
