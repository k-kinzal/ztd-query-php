<?php

declare(strict_types=1);

namespace LemonParser\Preprocessor;

/**
 * How deep the preprocessor is inside an excluded region, and where the region began.
 *
 * Lemon counts nested `%ifdef` directives inside an excluded region so that
 * only the matching `%endif` ends it.
 *
 * @visibility root
 */
final class Exclusion
{
    private int $depth = 0;

    private int $start = 0;

    private int $startLine = 1;

    /**
     * Answers how many directives deep the excluded region is.
     *
     * @return int Zero outside an excluded region
     */
    public function depth(): int
    {
        return $this->depth;
    }

    /**
     * Answers where the excluded region began.
     *
     * @return int The byte offset of its directive
     */
    public function start(): int
    {
        return $this->start;
    }

    /**
     * Answers the line the excluded region began on.
     *
     * @return int The line number
     */
    public function startLine(): int
    {
        return $this->startLine;
    }

    /**
     * Begins an excluded region.
     *
     * @param int $start The byte offset of its directive
     * @param int $line The line of its directive
     */
    public function enter(int $start, int $line): void
    {
        $this->depth = 1;
        $this->start = $start;
        $this->startLine = $line;
    }

    /**
     * Notes a further opening directive inside the excluded region.
     */
    public function nest(): void
    {
        $this->depth++;
    }

    /**
     * Notes a closing directive.
     *
     * @return bool True when it ends the excluded region
     */
    public function leave(): bool
    {
        return --$this->depth === 0;
    }
}
