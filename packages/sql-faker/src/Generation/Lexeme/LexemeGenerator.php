<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Lexeme;

/**
 * Supplies candidates without choosing one or mutating the generation state.
 */
interface LexemeGenerator
{
    /**
     * Null means this generator does not handle the input.
     */
    public function generate(LexemeInput $input): ?LexemeCandidates;
}
