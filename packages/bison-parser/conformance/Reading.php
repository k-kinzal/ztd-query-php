<?php

declare(strict_types=1);

namespace Conformance;

/**
 * What GNU Bison made of one grammar file.
 */
final class Reading
{
    /**
     * Messages Bison's scanner and parser produce; anything else Bison reports is about the grammar's meaning.
     */
    public const SYNTAX_MESSAGES = '/error: (syntax error|invalid (character|directive|identifier|null character|number after|character after|universal character)|unexpected |expected |an identifier expected|empty character literal|extra characters in character literal|missing .* at end of (file|line)|unterminated|too many)/';

    /**
     * @param string|null $report Path of the XML report, or null when Bison produced none
     * @param string|null $digest A digest of the report without its file name, or null when there is none
     * @param string $stderr What Bison wrote to its standard error
     * @param int $status Bison's exit status
     */
    public function __construct(
        public readonly ?string $report,
        public readonly ?string $digest,
        public readonly string $stderr,
        public readonly int $status,
    ) {
    }

    /**
     * Reports whether Bison produced a report.
     *
     * @return bool True when the grammar was accepted
     */
    public function accepted(): bool
    {
        return $this->report !== null;
    }

    /**
     * Reports whether Bison refused the file because of how it is written rather than what it says.
     *
     * @return bool True for a scanner or parser error
     */
    public function rejectsSyntax(): bool
    {
        return !$this->accepted() && preg_match(self::SYNTAX_MESSAGES, $this->stderr) === 1;
    }

    /**
     * Summarises the first error line for a report.
     *
     * @return string The first line of standard error mentioning an error, or the whole output cut short
     */
    public function firstError(): string
    {
        foreach (explode("\n", $this->stderr) as $line) {
            if (str_contains($line, ': error: ')) {
                return trim($line);
            }
        }

        return trim(substr($this->stderr, 0, 200));
    }
}
