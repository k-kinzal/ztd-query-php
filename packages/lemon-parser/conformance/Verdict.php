<?php

declare(strict_types=1);

namespace Conformance;

/**
 * How one grammar file came out of the comparison under one set of defines.
 */
enum Verdict: string
{
    case Identical = 'identical';
    case Mismatch = 'mismatch';
    case PreprocessMismatch = 'preprocessed text differs';
    case RejectedByBoth = 'rejected by both';
    case RejectedForMeaning = 'rejected by lemon for its meaning, read here';
    case ReadHereOnly = 'read here but rejected by lemon';
    case ReadByLemonOnly = 'read by lemon but rejected here';
    case ReprintRejected = 'reprint rejected by lemon';

    /**
     * Reports whether the verdict shows a difference from Lemon.
     *
     * @return bool True when the file counts as a failure
     */
    public function fails(): bool
    {
        return match ($this) {
            self::Identical, self::RejectedByBoth, self::RejectedForMeaning => false,
            self::Mismatch, self::PreprocessMismatch, self::ReadHereOnly, self::ReadByLemonOnly, self::ReprintRejected => true,
        };
    }
}
