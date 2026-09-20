<?php

declare(strict_types=1);

namespace LemonParser\Preprocessor;

use LemonParser\Ast\Location;
use LemonParser\SyntaxException;

/**
 * Settles `%if`, `%ifdef`, `%ifndef`, `%else` and `%endif` before the file is scanned.
 *
 * As in Lemon's `preprocess_input`, a directive counts only at the start
 * of a line, the directive lines and the excluded lines are blanked rather
 * than removed, so every position in the result is a position in the
 * original, and an excluded region may nest further directives.
 *
 * @visibility public
 *
 * @example Blanking a region that is not defined
 *     $preprocessor = new \LemonParser\Preprocessor\Preprocessor();
 *     $preprocessor->preprocess("a ::= B.\n%ifdef X\na ::= C.\n%endif\na ::= D.\n", []) // => "a ::= B.\n        \n        \n      \na ::= D.\n"
 *     $preprocessor->preprocess("%ifndef X || Y\na ::= C.\n%endif\n", ['Y']) // => "              \n        \n      \n"
 */
final class Preprocessor
{
    /**
     * Replaces excluded regions and directive lines with spaces.
     *
     * @param string $source The grammar file
     * @param list<string> $defines The names defined on the command line
     *
     * @return string The file with the same line breaks and directives settled
     *
     * @throws SyntaxException When a region is not closed or an expression is not well formed
     */
    public function preprocess(string $source, array $defines): string
    {
        $condition = new Condition($defines);
        $exclusion = new Exclusion();
        $line = 1;
        $length = strlen($source);
        for ($index = 0; $index < $length; $index++) {
            if ($source[$index] === "\n") {
                $line++;
            }
            if ($source[$index] !== '%' || ($index > 0 && $source[$index - 1] !== "\n")) {
                continue;
            }
            $directive = $this->directive($source, $index);
            if ($directive === null) {
                continue;
            }
            $source = $this->settle($source, $index, $directive, $exclusion, $condition, new Location($line, 1));
            $source = $this->blank($source, $index, $this->lineEnd($source, $index));
        }
        if ($exclusion->depth() > 0) {
            throw new SyntaxException('unterminated %ifdef starting on line ' . $exclusion->startLine(), new Location($exclusion->startLine(), 1));
        }

        return $source;
    }

    /**
     * Applies one directive to the excluded region and blanks the region it closes.
     *
     * @param string $source The file
     * @param int $index Where the `%` is
     * @param string $directive `endif`, `else`, `if`, `ifdef` or `ifndef`
     * @param Exclusion $exclusion The region being excluded, if any
     * @param Condition $condition Evaluates expressions
     * @param Location $location Where the directive is
     *
     * @return string The file with a closed region blanked
     */
    public function settle(string $source, int $index, string $directive, Exclusion $exclusion, Condition $condition, Location $location): string
    {
        if ($directive === 'endif') {
            return $exclusion->depth() > 0 && $exclusion->leave() ? $this->blank($source, $exclusion->start(), $index) : $source;
        }
        if ($directive === 'else') {
            if ($exclusion->depth() === 1) {
                $exclusion->leave();

                return $this->blank($source, $exclusion->start(), $index);
            }
            if ($exclusion->depth() === 0) {
                $exclusion->enter($index, $location->line);
            }

            return $source;
        }
        if ($exclusion->depth() > 0) {
            $exclusion->nest();
        } elseif ($this->opens($condition, $source, $index, $directive, $location)) {
            $exclusion->enter($index, $location->line);
        }

        return $source;
    }

    /**
     * Recognises the directive at a `%` as Lemon does.
     *
     * @param string $source The file
     * @param int $index Where the `%` is
     *
     * @return string|null `endif`, `else`, `ifdef`, `if`, `ifndef`, or null for anything else
     */
    public function directive(string $source, int $index): ?string
    {
        foreach (['endif' => 6, 'else' => 5] as $name => $width) {
            if (substr($source, $index, $width) === '%' . $name && ctype_space($source[$index + $width] ?? '')) {
                return $name;
            }
        }
        foreach (['ifdef', 'if', 'ifndef'] as $name) {
            if (substr($source, $index, strlen($name) + 2) === '%' . $name . ' ') {
                return $name;
            }
        }

        return null;
    }

    /**
     * Evaluates an opening directive and says whether it excludes what follows.
     *
     * @param Condition $condition Evaluates expressions
     * @param string $source The file
     * @param int $index Where the `%` is
     * @param string $directive `if`, `ifdef` or `ifndef`
     * @param Location $location Where the directive is
     *
     * @return bool True when the region is excluded
     */
    public function opens(Condition $condition, string $source, int $index, string $directive, Location $location): bool
    {
        $end = $this->lineEnd($source, $index);
        $keyword = strcspn($source, " \t\n\v\f\r", $index);
        $value = $condition->evaluate(substr($source, $index + $keyword, $end - $index - $keyword), $location);

        return $directive === 'ifndef' ? $value : !$value;
    }

    /**
     * Finds where the line holding an index ends.
     *
     * @param string $source The file
     * @param int $index An index in the line
     *
     * @return int The index of the newline, or the length of the file
     */
    public function lineEnd(string $source, int $index): int
    {
        $end = strpos($source, "\n", $index);

        return $end === false ? strlen($source) : $end;
    }

    /**
     * Replaces every byte but newlines with a space.
     *
     * @param string $source The file
     * @param int $from The first index to blank
     * @param int $to The index after the last one
     *
     * @return string The file with the region blanked
     */
    public function blank(string $source, int $from, int $to): string
    {
        $region = substr($source, $from, $to - $from);

        return substr($source, 0, $from) . preg_replace('/[^\n]/', ' ', $region) . substr($source, $to);
    }
}
