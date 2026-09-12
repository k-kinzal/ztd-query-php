<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Candidate;

use Override;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;

/**
 * The union of every applicable child's candidates; registration order is stable.
 */
final class ChoiceLexemeGenerator implements LexemeGenerator
{
    /**
     * @var list<LexemeGenerator>
     */
    private readonly array $generators;

    /**
     * Composes the union of every applicable child without giving duplicates additional probability.
     */
    public function __construct(LexemeGenerator ...$generators)
    {
        $this->generators = array_values($generators);
    }

    /**
     * Returns the lazy union of applicable candidates, retaining every source of duplicate output.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $results = [];
        foreach ($this->generators as $generator) {
            $result = $generator->generate($input);
            if ($result !== null) {
                $results[] = $result;
            }
        }
        return $results === [] ? null : new LexemeCandidates(function () use ($results): iterable {
            $seen = [];
            foreach ($results as $result) {
                foreach ($result->sequences() as $candidate) {
                    $key = $candidate->key();
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        yield new LexemeSequence(
                            $candidate->lexemes,
                            $candidate->id,
                            $candidate->left,
                            $candidate->boundaries,
                            fn (): iterable => $this->sources($results, $key),
                        );
                    }
                }
            }
        });
    }

    /**
     * Retains the provenance of semantically identical candidates without storing the whole union.
     * @param list<LexemeCandidates> $results
     * @return iterable<int, string>
     */
    public function sources(array $results, string $key): iterable
    {
        foreach ($results as $result) {
            foreach ($result->sequences() as $candidate) {
                if ($candidate->key() === $key) {
                    yield from $candidate->sources();
                }
            }
        }
    }
}
