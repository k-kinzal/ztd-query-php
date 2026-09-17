<?php

declare(strict_types=1);

namespace Conformance;

/**
 * The verdict on one grammar file and what explains it.
 */
final class Result
{
    /**
     * @param string $path The grammar file
     * @param Verdict $verdict How it came out
     * @param string $detail What explains the verdict, or an empty string
     */
    public function __construct(
        public readonly string $path,
        public readonly Verdict $verdict,
        public readonly string $detail = '',
    ) {
    }
}
