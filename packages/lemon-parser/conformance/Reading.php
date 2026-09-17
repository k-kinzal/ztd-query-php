<?php

declare(strict_types=1);

namespace Conformance;

/**
 * What Lemon made of one grammar file under one set of defines.
 */
final class Reading
{
    /**
     * Messages Lemon's preprocessor, tokenizer and reading states produce; anything else Lemon reports is about the grammar's meaning.
     */
    public const SYNTAX_MESSAGES = '/%if syntax error|unterminated %ifdef|not terminated before the end of the file|There is no prior rule|Code fragment beginning on this line|precedence symbol must be a terminal|Precedence mark on this line|Missing "\]" on precedence mark|Expected to see a ":"|is not a valid alias|Missing "\)" following|Missing "->" following|Too many symbols on RHS|Cannot form a compound|Illegal character on RHS|Illegal declaration keyword|Unknown declaration keyword|Symbol name missing after|already defined|has already be given a precedence|Can\x27t assign a precedence|Illegal argument to %|should be a token|More than one fallback|Extra wildcard|%token_class must be followed|already used|should be either "%" or a nonterminal name/';

    /**
     * @param string|null $report Lemon's report of the grammar, or null when Lemon refused it
     * @param string $preprocessed What `lemon -E` printed
     * @param string $stderr What Lemon wrote to its standard error and output while building
     * @param int $status Lemon's exit status
     */
    public function __construct(
        public readonly ?string $report,
        public readonly string $preprocessed,
        public readonly string $stderr,
        public readonly int $status,
    ) {
    }

    /**
     * Reports whether Lemon produced a report.
     *
     * @return bool True when the grammar was accepted
     */
    public function accepted(): bool
    {
        return $this->report !== null;
    }

    /**
     * Reports whether Lemon refused the file because of how it is written rather than what it says.
     *
     * @return bool True for a preprocessor, tokenizer or reader error
     */
    public function rejectsSyntax(): bool
    {
        return !$this->accepted() && preg_match(self::SYNTAX_MESSAGES, $this->stderr) === 1;
    }

    /**
     * Summarises the first error line for a report.
     *
     * @return string The first line mentioning an error, or the whole output cut short
     */
    public function firstError(): string
    {
        foreach (explode("\n", $this->stderr) as $line) {
            if (trim($line) !== '' && preg_match('/^(Parser statistics|\s)/', $line) !== 1) {
                return trim($line);
            }
        }

        return trim(substr($this->stderr, 0, 200));
    }
}
