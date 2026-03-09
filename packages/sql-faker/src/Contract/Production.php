<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use InvalidArgumentException;

final readonly class Production
{
    /**
     * @var list<Symbol>
     */
    public array $symbols;

    /**
     * @param array<array-key, mixed> $symbols
     */
    public function __construct(array $symbols)
    {
        if (!array_is_list($symbols)) {
            throw new InvalidArgumentException('Production symbols must be a list.');
        }

        foreach ($symbols as $symbol) {
            if (!$symbol instanceof Symbol) {
                throw new InvalidArgumentException('Production symbols must contain only Symbol values.');
            }
        }

        /** @var list<Symbol> $symbols */
        $this->symbols = $symbols;
    }

    /**
     * @return list<string>
     */
    public function references(): array
    {
        $references = [];
        foreach ($this->symbols as $symbol) {
            if ($symbol->isNonTerminal) {
                $references[] = $symbol->name;
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    public function sequence(): array
    {
        $sequence = [];
        foreach ($this->symbols as $symbol) {
            $sequence[] = ($symbol->isNonTerminal ? 'nt:' : 't:') . $symbol->name;
        }

        return $sequence;
    }
}
