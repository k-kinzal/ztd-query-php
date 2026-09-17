<?php

declare(strict_types=1);

namespace BisonParser;

use BisonParser\Ast\GrammarFile;
use BisonParser\Scanner\Scanner;
use BisonParser\Syntax\GrammarParser;
use BisonParser\Syntax\TokenStream;

/**
 * Reads a GNU Bison grammar file into a syntax tree that keeps everything the file says.
 *
 * The reader follows Bison's own `scan-gram.l` and `parse-gram.y`, so a
 * file Bison accepts is read the way Bison reads it, and a file Bison
 * rejects raises a `SyntaxException` naming the place. Host code is kept
 * as text, never interpreted.
 *
 * @visibility public
 *
 * @example Reading a grammar
 *     $file = (new \BisonParser\Parser())->parse("%token NUM\n%left '+'\n%%\nexpr: expr '+' expr | NUM ;\n");
 *     count($file->declarations) // => 2
 *     $file->rules()[0]->name->value // => 'expr'
 * @example Rejecting a file Bison rejects
 *     (new \BisonParser\Parser())->parse("%tokens A\n%%\ns: A;\n") // throws \BisonParser\SyntaxException: Invalid directive: %tokens at 1:1
 */
final class Parser
{
    /**
     * @param Scanner $scanner Splits the file into tokens
     * @param GrammarParser $grammar Builds the tree from the tokens
     */
    public function __construct(
        private readonly Scanner $scanner = new Scanner(),
        private readonly GrammarParser $grammar = new GrammarParser(),
    ) {
    }

    /**
     * Reads a grammar file.
     *
     * @param string $source Contents of the file
     *
     * @return GrammarFile The tree
     *
     * @throws SyntaxException When the file is not a grammar Bison would accept
     */
    public function parse(string $source): GrammarFile
    {
        return $this->grammar->parse(new TokenStream($this->scanner->scan($source)));
    }
}
