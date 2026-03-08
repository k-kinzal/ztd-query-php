<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Captures one alternative within a snapshotted grammar rule.
 */
final class GrammarAlternativeSnapshot
{
    /**
     * @param list<GrammarSymbolSnapshot> $symbols
     */
    public function __construct(
        public readonly array $symbols,
    ) {
    }

    /**
     * @return list<string>
     */
    public function references(): array
    {
        $references = [];
        foreach ($this->symbols as $symbol) {
            if ($symbol->isNonTerminal) {
                $references[] = $symbol->value;
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    public function sequence(): array
    {
        return array_map(
            static fn (GrammarSymbolSnapshot $symbol): string => ($symbol->isNonTerminal ? 'nt:' : 't:') . $symbol->value,
            $this->symbols,
        );
    }
}
