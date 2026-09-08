<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

use Override;

/**
 * Applies a source-defined uniqueness condition within one grammar scope.
 */
final class UniqueOptionRule implements RewriteRule
{
    /**
     * @param array<string, string> $categories First-terminal aliases sharing one source option slot
     */
    public function __construct(
        private readonly string $scope,
        private readonly string $option,
        private readonly array $categories,
        private readonly ?string $separator,
        private readonly string $source,
    ) {
    }

    /**
     * Retains the first occurrence and its full value, without merging independent or nested scopes.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $seen = [];
        foreach (array_reverse($sequence->occurrences($this->option)) as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $first = $sequence->terminals[$range[0]];
            $scope = $first->ancestor($this->scope);
            $category = $this->categories[$first->name] ?? null;
            if ($scope === null || $category === null) {
                continue;
            }
            if (isset($seen[$scope][$category])) {
                $start = $range[0];
                if ($this->separator !== null && $sequence->nameAt($start - 1) === $this->separator) {
                    --$start;
                }
                $sequence = $sequence->replace($start, $range[1] - $start, [], $this->source);
            }
            $seen[$scope][$category] = true;
        }
        return $sequence;
    }
}
