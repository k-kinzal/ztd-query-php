<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Candidate;

use Override;
use RuntimeException;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;

/**
 * Binds one exact release at construction without invoking unselected definitions.
 */
final class VersionedLexemeGenerator implements LexemeGenerator
{
    /**
     * The one reviewed case selected at construction, or null for an undeclared release.
     */
    public readonly ?VersionCase $selected;

    /**
     * Selects exactly one reviewed case at construction.
     * @throws RuntimeException When multiple cases declare the same release
     */
    public function __construct(string $version, VersionCase ...$cases)
    {
        $selected = null;
        foreach ($cases as $case) {
            if (!in_array($version, $case->versions, true)) {
                continue;
            }
            if ($selected !== null) {
                throw new RuntimeException('Overlapping version cases for ' . $version . ': ' . $selected->id . ', ' . $case->id);
            }
            $selected = $case;
        }
        $this->selected = $selected;
    }

    /**
     * Delegates only to the selected case; an undeclared release remains non-applicable.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $case = $this->selected;
        $result = $case?->generator->generate($input);
        if ($result === null) {
            return null;
        }
        return new LexemeCandidates(static function () use ($result, $case): iterable {
            foreach ($result->sequences() as $candidate) {
                yield new LexemeSequence(
                    $candidate->lexemes,
                    $candidate->id,
                    $candidate->left,
                    $candidate->boundaries,
                    static function () use ($candidate, $case): iterable {
                        yield from $candidate->sources();
                        yield 'version-case:' . $case->id;
                    }
                );
            }
        });
    }
}
