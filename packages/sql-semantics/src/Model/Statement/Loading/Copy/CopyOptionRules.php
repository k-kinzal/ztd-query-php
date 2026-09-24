<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The option combinations the server's COPY option reader rejects.
 * @visibility SqlSemantics
 */
final class CopyOptionRules
{
    /**
     * Options that only some formats accept.
     * @throws InvalidStructure
     */
    public static function format(CopyOptions $options): void
    {
        $binary = $options->format === CopyFormat::Binary;
        $csv = $options->format === CopyFormat::Csv;
        if ($binary && ($options->delimiter !== null || $options->null !== null || $options->default !== null || $options->header !== CopyHeader::Absent || $options->onError === CopyErrorAction::Ignore)) {
            throw new InvalidStructure('Binary COPY cannot specify DELIMITER, NULL, DEFAULT, HEADER or ON_ERROR ignore.');
        }
        if (!$csv && ($options->quote !== null || $options->escape !== null || $options->forceQuote !== null || $options->forceNotNull !== null || $options->forceNull !== null)) {
            throw new InvalidStructure('QUOTE, ESCAPE and the FORCE options require CSV format.');
        }
        if ($options->encoding === '') {
            throw new InvalidStructure('A COPY encoding requires a nonempty name.');
        }
    }

    /**
     * Single-byte delimiter, quote and escape characters that keep rows readable.
     * @throws InvalidStructure
     */
    public static function characters(CopyOptions $options): void
    {
        $delimiter = $options->effectiveDelimiter();
        $null = $options->effectiveNull();
        $quote = $options->quote ?? '"';
        if (strlen($delimiter) !== 1 || in_array($delimiter, ["\n", "\r"], true) || strlen($quote) !== 1 || strlen($options->escape ?? $quote) !== 1) {
            throw new InvalidStructure('COPY delimiter, quote and escape must be single one-byte characters other than newline and carriage return.');
        }
        if ($options->format === CopyFormat::Text && str_contains('\\.abcdefghijklmnopqrstuvwxyz0123456789', $delimiter)) {
            throw new InvalidStructure('A text COPY delimiter cannot be a backslash, a period, a lowercase letter or a digit.');
        }
        self::spellings($options, $delimiter, $null, $quote);
    }

    /**
     * NULL and DEFAULT spellings that stay distinguishable from delimiters, quotes, line ends and each other.
     * @throws InvalidStructure
     */
    public static function spellings(CopyOptions $options, string $delimiter, string $null, string $quote): void
    {
        if (strpbrk($null . ($options->default ?? ''), "\r\n") !== false || str_contains($null, $delimiter) || str_contains($options->default ?? '', $delimiter)) {
            throw new InvalidStructure('COPY NULL and DEFAULT spellings cannot contain newlines or the delimiter.');
        }
        if ($options->format === CopyFormat::Csv && ($delimiter === $quote || str_contains($null, $quote) || str_contains($options->default ?? '', $quote))) {
            throw new InvalidStructure('The CSV quote cannot be the delimiter or appear in the NULL or DEFAULT spelling.');
        }
        if ($options->default !== null && $options->default === $null) {
            throw new InvalidStructure('The NULL and DEFAULT spellings must differ.');
        }
    }

    /**
     * Options that only COPY FROM accepts, and the ones it rejects.
     * @throws InvalidStructure
     */
    public static function reading(CopyOptions $options): void
    {
        if ($options->forceQuote !== null) {
            throw new InvalidStructure('FORCE_QUOTE cannot be used with COPY FROM.');
        }
    }

    /**
     * Options that COPY TO rejects.
     * @throws InvalidStructure
     */
    public static function writing(CopyOptions $options): void
    {
        if ($options->forceNotNull !== null || $options->forceNull !== null || $options->freeze || $options->default !== null || $options->header === CopyHeader::Match || $options->onError !== null) {
            throw new InvalidStructure('COPY TO cannot use FORCE_NOT_NULL, FORCE_NULL, FREEZE, DEFAULT, HEADER MATCH or ON_ERROR.');
        }
    }
}
