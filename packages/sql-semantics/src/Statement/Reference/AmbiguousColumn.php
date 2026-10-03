<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

/**
 * At least two distinct declaration positions match a column name.
 * @visibility public
 * @example Identifying the semantic outcome
 *     class_exists(\SqlSemantics\Statement\Reference\AmbiguousColumn::class) // => true
 */
final class AmbiguousColumn
{
    /**
     * @var non-empty-list<ResolvedColumn>
     */
    public readonly array $matches;

    /**
     * Retains each occurrence and declaration instead of choosing a winner.
     */
    public function __construct(ResolvedColumn $first, ResolvedColumn $second, ResolvedColumn ...$rest)
    {
        $this->matches = [$first, $second, ...array_values($rest)];
    }
}
