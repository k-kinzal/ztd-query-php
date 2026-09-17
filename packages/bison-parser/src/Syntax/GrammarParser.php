<?php

declare(strict_types=1);

namespace BisonParser\Syntax;

use BisonParser\Ast\Epilogue;
use BisonParser\Ast\GrammarFile;
use BisonParser\Scanner\TokenKind;
use BisonParser\SyntaxException;

/**
 * Reads a whole grammar file, following the `input` rule of `parse-gram.y`.
 *
 * @visibility root
 */
final class GrammarParser
{
    /**
     * @param DeclarationParser $declarations Reads declarations
     * @param RuleParser $rules Reads rules
     */
    public function __construct(
        private readonly DeclarationParser $declarations = new DeclarationParser(),
        private readonly RuleParser $rules = new RuleParser(),
    ) {
    }

    /**
     * Reads the file.
     *
     * @param TokenStream $tokens Tokens of the whole file
     *
     * @return GrammarFile The file
     *
     * @throws SyntaxException When the file is not written as declarations, `%%`, rules and an optional epilogue
     */
    public function parse(TokenStream $tokens): GrammarFile
    {
        $declarations = [];
        while (!$tokens->is(TokenKind::Section)) {
            if ($tokens->accept(TokenKind::Semicolon) !== null) {
                continue;
            }
            if (!$this->declarations->starts($tokens->peek())) {
                throw SyntaxException::unexpected("a declaration or '%%'", $tokens->peek()->describe(), $tokens->peek()->location);
            }
            $declarations[] = $this->declarations->parse($tokens);
        }
        $tokens->next();
        $grammar = [];
        while (!$tokens->is(TokenKind::Section) && !$tokens->is(TokenKind::End)) {
            if ($tokens->accept(TokenKind::Semicolon) !== null) {
                continue;
            }
            if ($tokens->is(TokenKind::IdentifierColon)) {
                $grammar[] = $this->rules->parse($tokens);
            } elseif ($tokens->is(TokenKind::Line)) {
                $grammar[] = $this->declarations->parse($tokens);
            } elseif ($this->declarations->starts($tokens->peek())) {
                $grammar[] = $this->declarations->parse($tokens);
                $tokens->expect(TokenKind::Semicolon, "';' after a declaration among the rules");
            } else {
                throw SyntaxException::unexpected("a rule or ';'", $tokens->peek()->describe(), $tokens->peek()->location);
            }
        }
        $epilogue = null;
        if ($tokens->accept(TokenKind::Section) !== null) {
            $code = $tokens->expect(TokenKind::Epilogue, 'the epilogue');
            $epilogue = new Epilogue($code->text, $code->location);
        }
        $tokens->expect(TokenKind::End, 'the end of the file');

        return new GrammarFile($declarations, $grammar, $epilogue);
    }
}
