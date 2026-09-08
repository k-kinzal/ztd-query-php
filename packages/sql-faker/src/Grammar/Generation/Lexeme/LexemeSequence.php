<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Lexeme;

use Closure;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;

/**
 * All lexemes of one candidate, in output order, including any left boundary requirement.
 */
final class LexemeSequence
{
    /**
     * @param (Closure(): iterable<int, string>)|null $provenance
     * @param list<Lexeme> $lexemes
     * @param array<int, SpacingConstraint> $boundaries Constraints indexed by the right lexeme's position
     */
    public function __construct(
        public readonly array $lexemes,
        public readonly string $id,
        public readonly ?SpacingConstraint $left = null,
        public readonly array $boundaries = [],
        private readonly ?Closure $provenance = null,
    ) {
    }

    /**
     * Identifies the candidate's output and connection semantics independently of its source IDs.
     */
    public function key(): string
    {
        $boundaries = array_map(static fn (SpacingConstraint $boundary): int => $boundary->allowed, $this->boundaries);
        $left = $this->left->allowed ?? SpacingConstraint::EITHER;
        if ($this->lexemes !== []) {
            $left &= $boundaries[0] ?? SpacingConstraint::EITHER;
            unset($boundaries[0]);
        }
        $boundaries = array_filter($boundaries, static fn (int $allowed): bool => $allowed !== SpacingConstraint::EITHER);
        ksort($boundaries);
        return hash('sha256', serialize([
            array_map(static fn (Lexeme $lexeme): array => [
                $lexeme->text, $lexeme->kind, $lexeme->origin, $lexeme->phrase,
            ], $this->lexemes),
            $left,
            $boundaries,
        ]));
    }

    /**
     * Returns all definitions contributing this candidate without evaluating them during selection.
     * @return iterable<int, string>
     */
    public function sources(): iterable
    {
        if ($this->provenance === null) {
            yield $this->id;
            return;
        }
        yield from ($this->provenance)();
    }
}
