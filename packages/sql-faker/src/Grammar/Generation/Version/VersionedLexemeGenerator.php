<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Version;

use Override;
use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;

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
        return $this->selected?->generator->generate($input);
    }
}
