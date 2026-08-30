<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison;

use SqlFaker\Grammar\Source\Bison\Ast\BisonDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDirectiveReaderChain;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexeme;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTokenStream;

/**
 * Reads the declarations section of a Bison grammar.
 *
 * The section ends at the `%%` that opens the rules, and that separator is
 * consumed here so the rules reader starts on a rule. A declaration whose
 * arguments do not match its directive is dropped rather than aborting the
 * section: MySQL's grammar carries directives this parser has no model for, and
 * losing the declarations after one of them would cost more than the one.
 */
final class BisonPreambleReader
{
    /** @readonly */
    private BisonDirectiveReaderChain $directives;

    /**
     * @param BisonDirectiveReaderChain|null $directives Routes each directive to the reader that knows it
     */
    public function __construct(?BisonDirectiveReaderChain $directives = null)
    {
        $this->directives = $directives ?? BisonDirectiveReaderChain::forBisonGrammar();
    }

    /**
     * Reads the prologue and declarations up to the rules section.
     *
     * @param BisonTokenStream $stream Stream positioned at the start of the file
     *
     * @return BisonPreamble What the file stated before its rules
     */
    public function read(BisonTokenStream $stream): BisonPreamble
    {
        $prologue = null;
        /** @var list<BisonDeclaration> $declarations */
        $declarations = [];

        while ($stream->peek()->type !== BisonLexeme::Eof) {
            $lexeme = $stream->peek()->type;

            if ($lexeme === BisonLexeme::PercentPercent) {
                $stream->next();
                break;
            }

            if ($lexeme === BisonLexeme::Prologue) {
                $prologue = $stream->nextString();
                continue;
            }

            if ($lexeme !== BisonLexeme::Directive) {
                $stream->next();
                continue;
            }

            $declaration = $this->directives->read($stream, $stream->nextString());
            if ($declaration !== null) {
                $declarations[] = $declaration;
            }
        }

        return new BisonPreamble($prologue, $declarations);
    }
}
