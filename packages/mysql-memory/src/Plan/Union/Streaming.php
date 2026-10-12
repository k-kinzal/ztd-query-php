<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Union;

use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Combine\SetOperation;
use MySqlMemory\Plan\Path\Combine\UnionStream;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Plan\Path\Transform\Project;

/**
 * Lets a modern UNION limit stop its operands after enough distinct rows are read.
 * Sorting and materialization boundaries still consume their complete input.
 * SQL_CALC_FOUND_ROWS consumes the stream beyond the visible limit.
 * Verified through SQL with assignments on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class Streaming
{
    /**
     * Replaces consecutive UNION nodes, including their type-conversion projections.
     * A DISTINCT prefix below UNION ALL remains a materialization boundary.
     *
     * @param bool|null $deduplicate Whether the outermost union removes duplicates
     */
    public function apply(AccessPath $path, ?bool $deduplicate = null): AccessPath
    {
        if ($path instanceof Project) {
            return new Project($this->apply($path->input, $deduplicate), $path->expressions, $path->append, $path->retainAt, $path->publish);
        }
        if (!$path instanceof SetOperation || $path->kind !== SetKind::Union) {
            return $path;
        }
        $deduplicate ??= $path->distinct;
        if ($path->distinct && !$deduplicate) {
            return $path;
        }
        $left = $this->apply($path->left, $deduplicate);
        $stream = new UnionStream($left, $this->apply($path->right, $deduplicate), $path->width(), $path->retain, $this->prepares($left));

        return $path->distinct ? new Distinct($stream, $path->domains) : $stream;
    }

    /**
     * Identifies a materialized DISTINCT prefix, which executes even beneath LIMIT 0.
     */
    public function prepares(AccessPath $path): bool
    {
        while ($path instanceof Project) {
            $path = $path->input;
        }

        return $path instanceof UnionStream ? $path->prepare : $path instanceof SetOperation && $path->kind === SetKind::Union && $path->distinct;
    }
}
