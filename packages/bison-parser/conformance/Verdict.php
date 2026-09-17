<?php

declare(strict_types=1);

namespace Conformance;

/**
 * How one grammar file came out of the comparison.
 */
enum Verdict: string
{
    case Identical = 'identical';
    case Mismatch = 'mismatch';
    case RejectedByBoth = 'rejected by both';
    case RejectedForMeaning = 'rejected by bison for its meaning, read here';
    case ReadHereOnly = 'read here but rejected by bison';
    case ReadByBisonOnly = 'read by bison but rejected here';
    case ReprintRejected = 'reprint rejected by bison';
    case KnownTie = 'known bison tie, reports differ in symbol numbers only';

    /**
     * Reports whether the verdict shows a difference from Bison.
     *
     * @return bool True when the file counts as a failure
     */
    public function fails(): bool
    {
        return match ($this) {
            self::Identical, self::RejectedByBoth, self::RejectedForMeaning, self::KnownTie => false,
            self::Mismatch, self::ReadHereOnly, self::ReadByBisonOnly, self::ReprintRejected => true,
        };
    }
}
