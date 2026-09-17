<?php

declare(strict_types=1);

namespace Conformance;

/**
 * The verdict on one grammar file under one set of defines, and what explains it.
 */
final class Result
{
    /**
     * @param string $path The grammar file
     * @param list<string> $defines The defines it was read with
     * @param Verdict $verdict How it came out
     * @param string $detail What explains the verdict, or an empty string
     */
    public function __construct(
        public readonly string $path,
        public readonly array $defines,
        public readonly Verdict $verdict,
        public readonly string $detail = '',
    ) {
    }

    /**
     * Names the file and its defines.
     *
     * @return string The path, then the defines in brackets when there are any
     */
    public function label(): string
    {
        return $this->path . ($this->defines === [] ? '' : ' [' . implode(' ', $this->defines) . ']');
    }
}
