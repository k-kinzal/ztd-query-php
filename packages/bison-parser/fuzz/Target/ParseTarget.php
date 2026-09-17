<?php

declare(strict_types=1);

namespace Fuzz\Target;

use BisonParser\Ast\GrammarFile;
use BisonParser\Parser;
use BisonParser\Printer\Printer;
use BisonParser\SyntaxException;
use Error;

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
     * @param string $input Arbitrary text
     *
     * @return GrammarFile|null The tree, or null when the text is not a grammar
     */
    public function parse(string $input): ?GrammarFile
    {
        try {
            return $this->parser->parse($input);
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
}
