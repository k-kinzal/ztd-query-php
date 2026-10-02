<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Contract;

use SqlSemantics\Statement\Validation\Check;
use SqlSemantics\Statement\Validation\Snapshot;

/**
 * Explicit lexical switches implemented by the shipped MySQL parser.
 *
 * These do not infer runtime SQL modes or database session state. Other semantic
 * session inputs must be declared separately when a rule needs them.
 * @visibility public
 * @example Retaining the mode that changes double-quote interpretation
 *     (new \SqlSemantics\Statement\Contract\LexicalSettings('ANSI_QUOTES'))->ansiQuotes // => true
 */
final class LexicalSettings
{
    use Snapshot;

    /**
     * Double quotes delimit identifiers rather than strings.
     */
    public readonly bool $ansiQuotes;
    /**
     * The double-pipe operator requests concatenation.
     */
    public readonly bool $pipesAsConcat;
    /**
     * NOT uses its higher-precedence grammar interpretation.
     */
    public readonly bool $highNotPrecedence;
    /**
     * Backslashes remain ordinary characters in quoted strings.
     */
    public readonly bool $noBackslashEscapes;
    /**
     * Function names may be separated from their opening parenthesis.
     */
    public readonly bool $ignoreSpace;

    /**
     * Accepts explicit canonical lexer flags; unknown flags are configuration errors.
     */
    public function __construct(string $settings = '')
    {
        $flags = $settings === '' ? [] : explode(',', $settings);
        $known = ['ANSI_QUOTES', 'PIPES_AS_CONCAT', 'HIGH_NOT_PRECEDENCE', 'NO_BACKSLASH_ESCAPES', 'IGNORE_SPACE'];
        Check::input(array_diff($flags, $known) === [], 'The profile contains an unknown lexical setting.');
        $this->ansiQuotes = in_array('ANSI_QUOTES', $flags, true);
        $this->pipesAsConcat = in_array('PIPES_AS_CONCAT', $flags, true);
        $this->highNotPrecedence = in_array('HIGH_NOT_PRECEDENCE', $flags, true);
        $this->noBackslashEscapes = in_array('NO_BACKSLASH_ESCAPES', $flags, true);
        $this->ignoreSpace = in_array('IGNORE_SPACE', $flags, true);
    }

    /**
     * Compares closed immutable flags, independently of input order or duplication.
     */
    public function equals(self $other): bool
    {
        return $this->ansiQuotes === $other->ansiQuotes
            && $this->pipesAsConcat === $other->pipesAsConcat
            && $this->highNotPrecedence === $other->highNotPrecedence
            && $this->noBackslashEscapes === $other->noBackslashEscapes
            && $this->ignoreSpace === $other->ignoreSpace;
    }
}
