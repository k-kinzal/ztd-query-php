<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;

/**
 * A lazy Cartesian product of child outputs, all realizing the same terminal occurrence.
 */
final class SequenceLexemeGenerator implements LexemeGenerator
{
    /**
     * @var list<LexemeGenerator>
     */
    private readonly array $generators;

    /**
     * Composes complete child sequences in order, preserving their boundary constraints.
     */
    public function __construct(LexemeGenerator ...$generators)
    {
        $this->generators = array_values($generators);
    }

    /**
     * Builds the lazy Cartesian product of applicable child candidates.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $results = [];
        foreach ($this->generators as $generator) {
            $result = $generator->generate($input);
            if ($result === null) {
                return null;
            }
            $results[] = $result;
        }
        return new LexemeCandidates(fn (): iterable => $this->product($results, 0, new LexemeSequence([], 'sequence')));
    }

    /**
     * @param list<LexemeCandidates> $results
     * @return iterable<int, LexemeSequence>
     */
    public function product(array $results, int $index, LexemeSequence $prefix): iterable
    {
        if (!isset($results[$index])) {
            yield $prefix;
            return;
        }
        foreach ($results[$index]->sequences() as $sequence) {
            $offset = count($prefix->lexemes);
            $boundaries = $prefix->boundaries;
            foreach ($sequence->boundaries as $position => $constraint) {
                $boundaries[$offset + $position] = ($boundaries[$offset + $position] ?? new SpacingConstraint())->intersect($constraint);
            }
            if ($sequence->left !== null) {
                $boundaries[$offset] = ($boundaries[$offset] ?? new SpacingConstraint())->intersect($sequence->left);
            }
            yield from $this->product($results, $index + 1, new LexemeSequence(
                [...$prefix->lexemes, ...$sequence->lexemes],
                $prefix->id . '/' . $sequence->id,
                $prefix->left,
                $boundaries,
                static function () use ($prefix, $sequence): iterable {
                    yield from $prefix->sources();
                    yield from $sequence->sources();
                },
            ));
        }
    }
}
