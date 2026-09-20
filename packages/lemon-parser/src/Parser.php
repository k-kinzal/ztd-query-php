<?php

declare(strict_types=1);

namespace LemonParser;

use LemonParser\Ast\GrammarFile;
use LemonParser\Preprocessor\Preprocessor;
use LemonParser\Scanner\Scanner;
use LemonParser\Syntax\GrammarReader;
use LemonParser\Syntax\TokenStream;

/**
 * Reads a Lemon grammar file into a tree that keeps everything the file says.
 *
 * The file is read as Lemon reads it: `%ifdef` regions are settled with the
 * names given, the text is split into tokens the way `lemon.c` splits it,
 * and rules and declarations are accepted and rejected where Lemon accepts
 * and rejects them.
 *
 * @visibility public
 *
 * @example Reading a grammar
 *     $file = (new \LemonParser\Parser())->parse("%token_prefix TK_\n%left PLUS MINUS.\nexpr(A) ::= expr(B) PLUS expr(C). { A = B + C; }\nexpr(A) ::= NUM(B). { A = B; }\n");
 *     [count($file->declarations()), count($file->rules()), $file->rules()[1]->items[0]->symbols[0]->name] // => [2, 2, 'NUM']
 * @example Settling a conditional region
 *     $source = "%ifndef OMIT_WINDOW\nwindow ::= OVER.\n%endif\ncmd ::= SELECT.\n";
 *     $parser = new \LemonParser\Parser();
 *     [count($parser->parse($source)->rules()), count($parser->parse($source, ['OMIT_WINDOW'])->rules())] // => [2, 1]
 * @example Rejecting what Lemon rejects
 *     try { (new \LemonParser\Parser())->parse("expr ::= expr ? expr.\n"); } catch (\LemonParser\SyntaxException $e) { $error = $e->getMessage(); }
 *     $error // => 'Illegal character on RHS of rule: "?". at 1:15'
 */
final class Parser
{
    /**
     * @param Preprocessor $preprocessor Settles conditional regions
     * @param Scanner $scanner Splits the text into tokens
     * @param GrammarReader $reader Builds the tree
     */
    public function __construct(
        private readonly Preprocessor $preprocessor = new Preprocessor(),
        private readonly Scanner $scanner = new Scanner(),
        private readonly GrammarReader $reader = new GrammarReader(),
    ) {
    }

    /**
     * Reads a grammar file.
     *
     * @param string $source The file's text
     * @param list<string> $defines Names defined for `%ifdef`, as Lemon's `-D` option defines them
     *
     * @return GrammarFile The tree
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function parse(string $source, array $defines = []): GrammarFile
    {
        $tokens = $this->scanner->scan($this->preprocessor->preprocess($source, $defines));

        return $this->reader->read(new TokenStream($tokens));
    }
}
