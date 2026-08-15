<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Realizes parser terminals as SQL text and verifies the resulting token stream.
 */
interface LexicalGrammar
{
    public function version(): string;

    public function supports(string $terminal): bool;

    /**
     * @param list<string> $terminals
     */
    public function realize(array $terminals): string;
}
