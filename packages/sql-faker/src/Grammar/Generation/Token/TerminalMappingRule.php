<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

use Override;

/**
 * Maps a direct terminal child of one grammar production to a declared contextual domain.
 */
final class TerminalMappingRule implements RewriteRule
{
    /**
     * Binds a structural match and its source-defined replacement independently of dialect dispatch.
     */
    public function __construct(
        private readonly string $scope,
        private readonly string $from,
        private readonly string $to,
        private readonly string $source,
    ) {
    }

    /**
     * Replaces only direct children; identical terminals inside nested expressions remain unchanged.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name === $this->from && ($terminal->rules[count($terminal->rules) - 1] ?? null) === $this->scope) {
                $sequence = $sequence->replace($index, 1, [$terminal->replaced($this->to, $this->source)], $this->source);
            }
        }
        return $sequence;
    }
}
