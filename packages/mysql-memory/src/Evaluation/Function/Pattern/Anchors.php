<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

/**
 * Writes for PCRE what the dot and the anchors ^ $ \Z \b \B match in ICU, by the line terminators and the mode at the point being read.
 *
 * ICU ends a line at a line feed, a vertical tab, a form feed, a carriage return (with the line
 * feed after it), U+0085, U+2028 and U+2029; in the unix lines mode (d) only at a line feed.
 * Source: https://unicode-org.github.io/icu/userguide/strings/regexp.html.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Anchors
{
    /**
     * The line terminators, as a PCRE class.
     */
    public const TERMINATORS = '[\n\x{B}\f\r\x{85}\x{2028}\x{2029}]';

    /**
     * @param Translator $translator The translator whose mode holds
     */
    public function __construct(public readonly Translator $translator)
    {
    }

    /**
     * Answers what the dot matches: any character but a line terminator, unless the mode says otherwise.
     */
    public function dot(): string
    {
        $mode = $this->translator->mode;

        return match (true) {
            $mode->dotAll => '(?s:.)',
            $mode->unixLines => '[^\n]',
            default => '[^\n\x{B}\f\r\x{85}\x{2028}\x{2029}]',
        };
    }

    /**
     * Answers where ^ matches: at the start, and after a line terminator but at the end in the multiline mode.
     */
    public function caret(): string
    {
        $mode = $this->translator->mode;

        return match (true) {
            !$mode->multiline => '\A',
            $mode->unixLines => '(?:\A|(?<=\n)(?!\z))',
            default => '(?:\A|(?<=' . self::TERMINATORS . ')(?!(?<=\r)\n)(?!\z))',
        };
    }

    /**
     * Answers where $ matches: at the end or before a line terminator that ends the text, or before any in the multiline mode.
     */
    public function dollar(): string
    {
        $mode = $this->translator->mode;

        return match (true) {
            $mode->multiline && $mode->unixLines => '(?=\n|\z)',
            $mode->multiline => '(?:\z|(?=' . self::TERMINATORS . ')(?!(?<=\r)\n))',
            $mode->unixLines => '(?=\n?\z)',
            default => $this->end(),
        };
    }

    /**
     * Answers where \Z matches: at the end, or before a line terminator that ends the text.
     */
    public function end(): string
    {
        return '(?=(?:\r\n|' . self::TERMINATORS . ')?\z)(?!(?<=\r)\n)';
    }

    /**
     * Answers where \b matches, or \B when negated: between a word character and another; Unicode word boundaries (the w flag) are found the same way.
     */
    public function boundary(bool $negated, bool $words = false): string
    {
        $this->translator->located = $this->translator->located || $words;
        $word = '[' . implode('', Properties::WORD) . ']';

        return $negated ? '(?:(?<=' . $word . ')(?=' . $word . ')|(?<!' . $word . ')(?!' . $word . '))' : '(?:(?<=' . $word . ')(?!' . $word . ')|(?<!' . $word . ')(?=' . $word . '))';
    }
}
