<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use LemonParser\Ast\GrammarFile;
use LemonParser\Parser;
use LemonParser\Printer\Printer;
use LemonParser\SyntaxException;

/**
 * Exercises the parser with arbitrary text and checks what it promises.
 *
 * Any input must either yield a tree or raise a SyntaxException; anything
 * else escaping is a finding. A tree must print to text that reads back to
 * the same printed text, which is what makes the tree trustworthy.
 */
final class ParseTarget
{
    /**
     * @param Parser $parser Reads grammar files
     * @param Printer $printer Writes trees back out
     */
    public function __construct(
        private readonly Parser $parser = new Parser(),
        private readonly Printer $printer = new Printer(),
    ) {
    }

    /**
     * Parses the input, tolerating only a syntax error.
     *
     * @param string $input Arbitrary text; a first line starting with `%D` names defines, separated by spaces
     *
     * @return GrammarFile|null The tree, or null when the text is not a grammar
     */
    public function parse(string $input): ?GrammarFile
    {
        [$source, $defines] = $this->split($input);
        try {
            return $this->parser->parse($source, $defines);
        } catch (SyntaxException) {
            return null;
        }
    }

    /**
     * Parses the input and, when it is a grammar, checks that printing it is stable.
     *
     * @param string $input Arbitrary text
     *
     * @throws Error When the printed grammar does not read back to the same text
     */
    public function roundTrip(string $input): void
    {
        $file = $this->parse($input);
        if ($file === null) {
            return;
        }
        $printed = $this->printer->print($file);
        try {
            $again = $this->printer->print($this->parser->parse($printed));
        } catch (SyntaxException $exception) {
            throw new Error("The printed grammar does not parse\nInput (hex): " . bin2hex($input) . "\nPrinted:\n{$printed}\nError: {$exception->getMessage()}", 0, $exception);
        }
        if ($again !== $printed) {
            throw new Error("Printing is not stable\nInput (hex): " . bin2hex($input) . "\nFirst:\n{$printed}\nSecond:\n{$again}");
        }
    }

    /**
     * Splits a leading `%D` line into defines so the fuzzer reaches conditional regions.
     *
     * @param string $input Arbitrary text
     *
     * @return array{string, list<string>} The grammar text and the defines
     */
    public function split(string $input): array
    {
        if (!str_starts_with($input, '%D')) {
            return [$input, []];
        }
        $end = strpos($input, "\n");
        $line = $end === false ? substr($input, 2) : substr($input, 2, $end - 2);
        $defines = array_values(array_filter(explode(' ', $line), static fn (string $name): bool => $name !== ''));

        return [$end === false ? '' : substr($input, $end + 1), $defines];
    }
}
