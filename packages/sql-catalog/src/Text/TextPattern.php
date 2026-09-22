<?php

declare(strict_types=1);

namespace SqlCatalog\Text;

/**
 * A string the analyzer knows in part: resolved runs of text with gaps between them.
 *
 * A pattern with no gaps is an exact string. One with gaps describes the shape
 * of every string the expression can produce, which is what the catalog reports
 * when a query is assembled from values that are not statically known.
 *
 * @visibility root
 */
final class TextPattern
{
    /**
     * @param list<TextSegment> $segments Segments in source order, with no two adjacent literals
     */
    private function __construct(public readonly array $segments)
    {
    }

    /**
     * The empty pattern, which concatenation leaves unchanged.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * A pattern that resolves to exactly the given characters.
     */
    public static function fromText(string $text): self
    {
        return $text === '' ? new self([]) : new self([new LiteralText($text)]);
    }

    /**
     * A pattern consisting of one unresolved gap.
     */
    public static function fromHole(TextHole $hole): self
    {
        return new self([$hole]);
    }

    /**
     * A pattern built from arbitrary segments, coalescing adjacent literals.
     *
     * @param list<TextSegment> $segments
     */
    public static function fromSegments(array $segments): self
    {
        $normalized = [];
        foreach ($segments as $segment) {
            $last = $normalized === [] ? null : $normalized[count($normalized) - 1];
            if ($segment instanceof LiteralText && $segment->text === '') {
                continue;
            }
            if ($segment instanceof LiteralText && $last instanceof LiteralText) {
                $normalized[count($normalized) - 1] = new LiteralText($last->text . $segment->text);
                continue;
            }
            $normalized[] = $segment;
        }

        return new self($normalized);
    }

    /**
     * The pattern produced by writing this one and then the other.
     */
    public function concat(self $other): self
    {
        return self::fromSegments(array_merge($this->segments, $other->segments));
    }

    /**
     * Whether every character of the string is known.
     */
    public function isExact(): bool
    {
        return $this->holes() === [];
    }

    /**
     * The exact string, or null when the pattern still has gaps.
     */
    public function text(): ?string
    {
        $text = '';
        foreach ($this->segments as $segment) {
            if (!$segment instanceof LiteralText) {
                return null;
            }
            $text .= $segment->text;
        }

        return $text;
    }

    /**
     * The gaps, in source order.
     *
     * @return list<TextHole>
     */
    public function holes(): array
    {
        $holes = [];
        foreach ($this->segments as $segment) {
            if ($segment instanceof TextHole) {
                $holes[] = $segment;
            }
        }

        return $holes;
    }

    /**
     * The pattern rendered for humans, with every gap shown as `{$}`.
     */
    public function display(): string
    {
        return $this->render('{$}');
    }

    /**
     * The pattern rendered with a caller-chosen stand-in for every gap.
     *
     * Reading placeholders out of partially resolved SQL needs a stand-in that
     * lexes as an ordinary word, so that a gap inside a quoted string does not
     * end the string it sits in.
     */
    public function render(string $holeText): string
    {
        $rendered = '';
        foreach ($this->segments as $segment) {
            $rendered .= $segment instanceof TextHole ? $holeText : $segment->display();
        }

        return $rendered;
    }

    /**
     * A canonical string that distinguishes patterns differing only in their gaps.
     */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->segments as $segment) {
            $parts[] = match ($segment::class) {
                LiteralText::class => 'text:' . $segment->display(),
                TextHole::class => 'hole:' . $segment->origin->value,
                default => 'segment:' . $segment->display(),
            };
        }

        return implode("\x1f", $parts);
    }

    /**
     * Whether the two patterns describe the same string shape.
     */
    public function equals(self $other): bool
    {
        return $this->signature() === $other->signature();
    }

    /**
     * Whether the pattern contributes no characters at all.
     */
    public function isEmpty(): bool
    {
        return $this->segments === [];
    }
}
