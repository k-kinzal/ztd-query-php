<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Candidate;

use Override;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;

/**
 * Supplies spellings from a fixed upstream registration table after explicit handler selection.
 */
final class RegisteredLexemeGenerator implements LexemeGenerator
{
    /**
     * @param array<string, list<string>> $registrations
     * @param array<string, string> $aliases Parser lookahead names mapped to their registered base token
     */
    public function __construct(
        private readonly array $registrations,
        private readonly string $definition,
        private readonly array $aliases = [],
    ) {
    }

    /**
     * Preserves the complete spelling set of the selected registration.
     * @throws LexicalException When a declared handler's required registration is missing
     */
    #[Override]
    public function generate(LexemeInput $input): LexemeCandidates
    {
        $terminal = $input->terminal()->name;
        $base = $this->aliases[$terminal] ?? $terminal;
        $spellings = $this->registrations[$base] ?? [];
        if ($spellings === []) {
            throw new LexicalException('Missing registration for ' . $terminal . ' in ' . $this->definition);
        }
        $candidates = [];
        foreach ($spellings as $spelling) {
            $candidates[] = new LexemeSequence([
                new Lexeme($spelling, 'keyword', $input->terminal(), $this->definition),
            ], $this->definition . ':' . $spelling);
        }
        return LexemeCandidates::of(...$candidates);
    }
}
