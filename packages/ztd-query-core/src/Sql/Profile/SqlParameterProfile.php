<?php

declare(strict_types=1);

namespace ZtdQuery\Sql\Profile;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\LexicalPattern;

/**
 * How one dialect writes a placeholder for a value the driver will bind.
 *
 * A placeholder is written either by position or by name, and a dialect
 * decides what spells each, what may join a name that is spelled in parts,
 * what a name may carry after it, and what a prefix is not a placeholder
 * after, because the same character often spells an operator as well.
 */
final class SqlParameterProfile
{
    /** @var list<non-empty-string> */
    private readonly array $positionalParameterPatterns;

    /** @var array<non-empty-string, list<non-empty-string>> */
    private readonly array $namedParameterSeparators;

    /** @var array<non-empty-string, non-empty-string> */
    private readonly array $namedParameterSuffixPatterns;

    /** @var array<non-empty-string, list<non-empty-string>> */
    private readonly array $namedParameterForbiddenPredecessors;

    /**
     * @param list<string> $positionalParameterPatterns Patterns a positional parameter is written as
     * @param array<string, list<string>> $namedParameterSeparators Parameter prefix => what may separate it from its name
     * @param array<string, string> $namedParameterSuffixPatterns Parameter prefix => pattern for what may follow its name
     * @param array<string, list<string>> $namedParameterForbiddenPredecessors Parameter prefix => what it is not a parameter after
     * @param LexicalPattern $patterns Reads a regular expression against a position
     * @param LexicalDelimiters $delimiters Refuses lexical data a scanner could not use
     *
     * @throws InvalidDefinitionException When a delimiter is empty or a pattern is unreadable
     */
    public function __construct(
        array $positionalParameterPatterns,
        array $namedParameterSeparators,
        array $namedParameterSuffixPatterns,
        array $namedParameterForbiddenPredecessors,
        private readonly LexicalPattern $patterns = new LexicalPattern(),
        LexicalDelimiters $delimiters = new LexicalDelimiters(),
    ) {
        $this->positionalParameterPatterns = $delimiters->validPatterns($positionalParameterPatterns);
        $this->namedParameterSeparators = $delimiters->perPrefixLists($namedParameterSeparators);
        $this->namedParameterSuffixPatterns = $delimiters->perPrefixPatterns($namedParameterSuffixPatterns);
        $this->namedParameterForbiddenPredecessors = $delimiters->perPrefixLists(
            $namedParameterForbiddenPredecessors,
        );
    }

    /**
     * Answers how long the positional parameter starting here is.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int Its length, or zero when none starts there
     */
    public function positionalParameterLengthAt(string $sql, int $offset): int
    {
        foreach ($this->positionalParameterPatterns as $pattern) {
            $match = $this->patterns->matchAt($pattern, $sql, $offset);
            if ($match !== null) {
                return strlen($match);
            }
        }

        return 0;
    }

    /**
     * Answers the prefix of the named parameter starting here, if any.
     *
     * A prefix is not always a parameter: some dialects write the same character
     * as part of an operator, and what comes before it is what tells them apart.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The prefix, or null when no parameter starts there
     */
    public function namedParameterPrefixAt(string $sql, int $offset): ?string
    {
        foreach (array_keys($this->namedParameterSeparators) as $prefix) {
            if (substr_compare($sql, $prefix, $offset, strlen($prefix)) !== 0) {
                continue;
            }
            foreach ($this->namedParameterForbiddenPredecessors[$prefix] ?? [] as $forbidden) {
                if ($offset >= strlen($forbidden)
                    && substr_compare($sql, $forbidden, $offset - strlen($forbidden), strlen($forbidden)) === 0
                ) {
                    continue 2;
                }
            }

            return $prefix;
        }

        return null;
    }

    /**
     * Answers what separates a parameter prefix from its name here, if anything.
     *
     * @param string $prefix Prefix the parameter was written with
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The separator, or null when none is written there
     */
    public function parameterNameSeparatorAt(string $prefix, string $sql, int $offset): ?string
    {
        foreach ($this->namedParameterSeparators[$prefix] ?? [] as $separator) {
            if (substr_compare($sql, $separator, $offset, strlen($separator)) === 0) {
                return $separator;
            }
        }

        return null;
    }

    /**
     * Answers how much a parameter written with this prefix carries after its name.
     *
     * @param string $prefix Prefix the parameter was written with
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int How long it is, or zero when nothing follows
     */
    public function parameterSuffixLength(string $prefix, string $sql, int $offset): int
    {
        $match = $this->patterns->matchAt($this->namedParameterSuffixPatterns[$prefix] ?? null, $sql, $offset);

        return $match === null ? 0 : strlen($match);
    }
}
