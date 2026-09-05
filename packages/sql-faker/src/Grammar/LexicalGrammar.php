<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Realizes parser terminals as SQL text and verifies the resulting token stream.
 *
 * Every dialect writes its terminals differently, but all of them owe the same
 * guarantee: text written from a terminal sequence must read back as that same
 * sequence through the dialect's own lexer. An implementation that only wrote
 * text would let a generator drift away from the server it claims to target.
 */
interface LexicalGrammar
{
    /**
     * Writes the one lexeme a lexical generation plan asks for.
     *
     * @param GenerationPlan<bool> $plan Plan naming the lexeme kind and its bounds
     *
     * @return non-empty-string The lexeme
     */
    public function generate(GenerationPlan $plan): string;

    /**
     * Names the server version this grammar generates for.
     *
     * @return string Profile version, e.g. "mysql-8.4.7"
     */
    public function version(): string;

    /**
     * Reports whether a parser terminal can be written as SQL.
     *
     * @param string $terminal Terminal to look for
     *
     * @return bool True when the terminal can be realized
     */
    public function supports(string $terminal): bool;

    /**
     * Writes a terminal sequence as SQL and checks that it reads back as itself.
     *
     * @param list<string> $terminals Terminals to write, in order
     * @param GenerationPlan<bool>|null $plan Plan that may pin exact lexemes for some terminals
     *
     * @return string SQL that tokenizes back to the terminals it was written from
     */
    public function realize(array $terminals, ?GenerationPlan $plan = null): string;
}
