<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\GrammarSourceException;

/**
 * Applies the `%if`, `%ifdef`, `%ifndef`, `%else` and `%endif` directives of a Lemon grammar.
 *
 * Lines of an inactive branch are blanked rather than removed, so line
 * numbers in later errors still refer to the file as written.
 *
 * @visibility root
 */
final class LemonPreprocessor
{
    /**
     * @param LemonCondition $condition Evaluates the conditions
     */
    public function __construct(private readonly LemonCondition $condition = new LemonCondition())
    {
    }

    /**
     * Blanks the lines of every inactive branch.
     *
     * @param string $source Grammar as written
     *
     * @return string Grammar with the same line count and only the active branches
     *
     * @throws GrammarSourceException When the directives do not nest
     */
    public function process(string $source): string
    {
        $lines = explode("\n", $source);
        $stack = [];
        $active = true;
        foreach ($lines as $number => $line) {
            if (preg_match('/^%(if|ifdef|ifndef|else|endif)\b(.*)$/', $line, $match) !== 1) {
                $lines[$number] = $active ? $line : '';
                continue;
            }
            $lines[$number] = '';
            $directive = $match[1];
            if ($directive === 'endif' || $directive === 'else') {
                $frame = array_pop($stack);
                if ($frame === null || ($directive === 'else' && $frame['else'])) {
                    throw GrammarSourceException::unexpected('an open conditional', "%{$directive}", $number + 1);
                }
                $active = $frame['outer'];
                if ($directive === 'else') {
                    $stack[] = ['outer' => $frame['outer'], 'taken' => $frame['taken'], 'else' => true];
                    $active = $frame['outer'] && !$frame['taken'];
                }
                continue;
            }
            $taken = $active && $this->condition->evaluate($this->conditionOf($directive, $match[2]));
            $taken = $directive === 'ifndef' ? ($active && !$taken) : $taken;
            $stack[] = ['outer' => $active, 'taken' => $taken, 'else' => false];
            $active = $taken;
        }
        if ($stack !== []) {
            throw GrammarSourceException::unterminated('conditional directive', count($lines));
        }

        return implode("\n", $lines);
    }

    /**
     * Answers the condition a directive tests, without any trailing comment.
     *
     * @param string $directive The directive name
     * @param string $rest Text after the directive
     *
     * @return string The condition
     */
    public function conditionOf(string $directive, string $rest): string
    {
        $rest = trim((string) preg_replace('#//.*$|/\*.*?\*/#', '', $rest));
        if ($directive === 'if') {
            return $rest;
        }
        $name = strtok($rest, " \t");

        return $name === false ? '' : $name;
    }
}
