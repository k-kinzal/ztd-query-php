<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Candidate;

use Override;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;

/**
 * A fixed output element, including an explicit empty output when text is empty.
 */
final class FixedLexemeGenerator implements LexemeGenerator
{
    /**
     * Declares one complete fixed lexeme and its source identity.
     */
    public function __construct(
        private readonly string $text,
        private readonly string $kind,
        private readonly string $definition,
        private readonly ?string $phrase = null,
    ) {
    }

    /**
     * Realizes the declared lexeme at the current terminal occurrence.
     */
    #[Override]
    public function generate(LexemeInput $input): LexemeCandidates
    {
        $lexemes = $this->text === '' ? [] : [
            new Lexeme($this->text, $this->kind, $input->terminal(), $this->definition, $this->phrase),
        ];
        return LexemeCandidates::of(new LexemeSequence($lexemes, $this->definition . ':' . $this->text));
    }
}
