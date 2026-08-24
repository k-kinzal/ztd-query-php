<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison;

use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Rule\BisonRuleReader;

/**
 * Reads a GNU Bison or Yacc grammar file into an AST.
 *
 * A grammar file has three sections in a fixed order — declarations, rules,
 * epilogue — so parsing is a sequence of three reads rather than a decision.
 * Each section knows its own end, which is what lets this class say what a
 * grammar file is without also saying how any part of it is spelled.
 *
 * A file with no rules is refused. Everything else the parser does not
 * understand is carried through rather than dropped, because MySQL's grammar
 * uses constructs no model here covers and the rules after one of them are
 * still worth having.
 */
final class BisonParser
{
    /** @readonly */
    private BisonPreambleReader $preamble;

    /** @readonly */
    private BisonRuleReader $rules;

    /** @readonly */
    private BisonStartSymbol $startSymbol;

    /**
     * @param BisonPreambleReader|null $preamble Reads the prologue and declarations
     * @param BisonRuleReader|null $rules Reads the production rules
     * @param BisonStartSymbol|null $startSymbol Decides which rule a derivation begins from
     */
    public function __construct(
        ?BisonPreambleReader $preamble = null,
        ?BisonRuleReader $rules = null,
        ?BisonStartSymbol $startSymbol = null,
    ) {
        $this->preamble = $preamble ?? new BisonPreambleReader();
        $this->rules = $rules ?? new BisonRuleReader();
        $this->startSymbol = $startSymbol ?? new BisonStartSymbol();
    }

    /**
     * Reads grammar source text into an AST.
     *
     * @param string $input Bison grammar text
     *
     * @return BisonAst The grammar the text describes
     *
     * @throws GrammarParseException When the text yields no production rules
     */
    public function parse(string $input): BisonAst
    {
        $stream = BisonTokenStream::over($input);

        $preamble = $this->preamble->read($stream);
        $rules = $this->rules->readAll($stream);
        $epilogue = trim($stream->consumeRemaining());

        if ($rules === []) {
            throw GrammarParseException::noRulesParsed('Bison');
        }

        return new BisonAst(
            $this->startSymbol->from($preamble->declarations, $rules),
            $preamble->prologue,
            $preamble->declarations,
            $rules,
            $epilogue !== '' ? $epilogue : null,
        );
    }

    /**
     * Reads a grammar file from disk.
     *
     * @param string $path Path to a Bison grammar file
     *
     * @return BisonAst The grammar the file describes
     *
     * @throws GrammarParseException When the file cannot be read, or yields no production rules
     */
    public function parseFile(string $path): BisonAst
    {
        if (!is_file($path)) {
            throw GrammarParseException::unreadableSource($path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw GrammarParseException::unreadableSource($path);
        }

        return $this->parse($contents);
    }
}
