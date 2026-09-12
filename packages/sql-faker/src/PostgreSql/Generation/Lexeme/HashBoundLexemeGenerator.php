<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use Override;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;

/**
 * Implements the modulus/remainder name checks in PostgreSQL 17.2 gram.y/PartitionBoundSpec.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class HashBoundLexemeGenerator implements LexemeGenerator
{
    /**
     * Both orders remain reachable; a previously selected name in the same bound is not repeated.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== 'HASH_BOUND_NAME') {
            return null;
        }
        $scope = $input->terminal()->ancestor('PartitionBoundSpec');
        $taken = [];
        foreach ($input->right->parts as $part) {
            if ($part->lexeme->origin->name === 'HASH_BOUND_NAME'
                && $part->lexeme->origin->ancestor('PartitionBoundSpec') === $scope) {
                $taken[] = strtolower($part->lexeme->text);
            }
        }
        $candidates = [];
        foreach ($input->requested === null ? ['modulus', 'remainder'] : [$input->requested] as $word) {
            $name = strtolower($word);
            if (in_array($name, ['modulus', 'remainder'], true) && !in_array($name, $taken, true)) {
                $candidates[] = new LexemeSequence([
                    new Lexeme($word, 'identifier', $input->terminal(), 'gram.y:PartitionBoundSpec'),
                ], 'postgresql.hash-bound:' . $word);
            }
        }
        return LexemeCandidates::of(...$candidates);
    }
}
