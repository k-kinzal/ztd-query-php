<?php

declare(strict_types=1);

namespace SqlCatalog\Text;

use SqlCatalog\Type\TypeShape;

/**
 * Collapses several patterns into the one shape that covers all of them.
 *
 * Joining branches or iterations of a loop can produce more strings than a
 * catalog should list. Generalization keeps the text the alternatives agree on
 * and replaces the part they disagree on with a single gap, so the reported
 * shape still matches every string the code can build.
 *
 * @visibility root
 */
final class TextGeneralization
{
    /**
     * @param Origin $origin The origin recorded on gaps this generalization introduces
     */
    public function __construct(public readonly Origin $origin)
    {
    }

    /**
     * The narrowest pattern that covers both inputs.
     */
    public function merge(TextPattern $left, TextPattern $right): TextPattern
    {
        if ($left->equals($right)) {
            return $left;
        }

        $resolved = $this->mergeResolved($left, $right);
        if ($resolved !== null) {
            return $resolved;
        }

        $leftAtoms = $this->atoms($left);
        $rightAtoms = $this->atoms($right);
        $prefix = $this->commonPrefixLength($leftAtoms, $rightAtoms);
        $remaining = min(count($leftAtoms), count($rightAtoms)) - $prefix;
        $suffix = $this->commonSuffixLength($leftAtoms, $rightAtoms, $remaining);

        $segments = array_merge(
            $this->rebuild(array_slice($leftAtoms, 0, $prefix)),
            [new TextHole($this->origin, $this->gapType($left, $right))],
            $this->rebuild($suffix === 0 ? [] : array_slice($leftAtoms, -$suffix)),
        );

        return TextPattern::fromSegments($segments);
    }

    /**
     * The narrowest pattern covering two fully resolved strings, or null when either has a gap.
     *
     * Statements are long and are merged often, so the common case of two
     * resolved strings is answered by comparing the strings themselves rather
     * than by walking them character by character.
     */
    public function mergeResolved(TextPattern $left, TextPattern $right): ?TextPattern
    {
        $leftText = $left->text();
        $rightText = $right->text();
        if ($leftText === null || $rightText === null) {
            return null;
        }

        $prefix = $this->sharedPrefixLength($leftText, $rightText);
        $limit = min(strlen($leftText), strlen($rightText)) - $prefix;
        $suffix = min($limit, $this->sharedPrefixLength(strrev($leftText), strrev($rightText)));

        return TextPattern::fromSegments([
            new LiteralText(substr($leftText, 0, $prefix)),
            new TextHole($this->origin, TypeShape::of(['string'])),
            new LiteralText($suffix === 0 ? '' : substr($leftText, -$suffix)),
        ]);
    }

    /**
     * How many leading bytes two strings share.
     */
    public function sharedPrefixLength(string $left, string $right): int
    {
        return strspn($left ^ $right, "\0");
    }

    /**
     * The narrowest pattern that covers every input.
     *
     * @param list<TextPattern> $patterns
     */
    public function mergeAll(array $patterns): TextPattern
    {
        $merged = array_shift($patterns);
        if ($merged === null) {
            return TextPattern::empty();
        }

        foreach ($patterns as $pattern) {
            $merged = $this->merge($merged, $pattern);
        }

        return $merged;
    }

    /**
     * The pattern split into single characters and whole gaps, so the two sides align.
     *
     * @return list<string|TextHole>
     */
    public function atoms(TextPattern $pattern): array
    {
        $atoms = [];
        foreach ($pattern->segments as $segment) {
            if ($segment instanceof TextHole) {
                $atoms[] = $segment;
                continue;
            }
            if (!$segment instanceof LiteralText) {
                continue;
            }
            $length = strlen($segment->text);
            for ($offset = 0; $offset < $length; $offset++) {
                $atoms[] = $segment->text[$offset];
            }
        }

        return $atoms;
    }

    /**
     * How many leading atoms the two sides share.
     *
     * @param list<string|TextHole> $left
     * @param list<string|TextHole> $right
     */
    public function commonPrefixLength(array $left, array $right): int
    {
        $limit = min(count($left), count($right));
        $shared = 0;
        while ($shared < $limit && $this->sameAtom($left[$shared], $right[$shared])) {
            $shared++;
        }

        return $shared;
    }

    /**
     * How many trailing atoms the two sides share, without reaching into the prefix.
     *
     * @param list<string|TextHole> $left
     * @param list<string|TextHole> $right
     * @param int $limit The atoms still available after the shared prefix
     */
    public function commonSuffixLength(array $left, array $right, int $limit): int
    {
        $leftLast = count($left) - 1;
        $rightLast = count($right) - 1;
        $shared = 0;
        while (
            $shared < $limit
            && $this->sameAtom($left[$leftLast - $shared], $right[$rightLast - $shared])
        ) {
            $shared++;
        }

        return $shared;
    }

    /**
     * Whether two atoms stand for the same thing.
     *
     * @param string|TextHole $left
     * @param string|TextHole $right
     */
    public function sameAtom(string|TextHole $left, string|TextHole $right): bool
    {
        if ($left instanceof TextHole || $right instanceof TextHole) {
            return $left instanceof TextHole
                && $right instanceof TextHole
                && $left->origin === $right->origin;
        }

        return $left === $right;
    }

    /**
     * Segments rebuilt from atoms.
     *
     * @param list<string|TextHole> $atoms
     * @return list<TextSegment>
     */
    public function rebuild(array $atoms): array
    {
        $segments = [];
        foreach ($atoms as $atom) {
            $segments[] = $atom instanceof TextHole ? $atom : new LiteralText($atom);
        }

        return $segments;
    }

    /**
     * The type recorded on the introduced gap.
     */
    public function gapType(TextPattern $left, TextPattern $right): TypeShape
    {
        return $left->isExact() && $right->isExact() ? TypeShape::of(['string']) : TypeShape::unknown();
    }
}
